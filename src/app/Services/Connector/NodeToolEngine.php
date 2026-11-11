<?php

namespace App\Services\Connector;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Runs the tools of the @netsendo/mcp-client package (mcp/ in the repository)
 * through its bridge.js, so the 140 tools are defined once, in that package.
 *
 * One Node.js process per call. The bridge speaks JSON lines on stdin/stdout:
 * it asks for API requests ("api") and this class answers them ("api_response")
 * by running the request in the application, then the bridge writes the tool
 * result ("result"). See mcp/src/bridge.ts for the protocol.
 *
 * The process gets no credentials and none of the application's environment.
 */
class NodeToolEngine implements ToolEngine
{
    /** @var list<object>|null */
    private ?array $tools = null;

    public function available(): bool
    {
        try {
            return $this->tools() !== [];
        } catch (RuntimeException) {
            return false;
        }
    }

    public function tools(): array
    {
        if ($this->tools !== null) {
            return $this->tools;
        }

        $bridge = $this->bridgePath();
        // A new build of the package is a new file, so the cache follows it.
        $cacheKey = 'connector.tools.'.md5($bridge.'|'.filemtime($bridge).'|'.filesize($bridge));

        $json = Cache::get($cacheKey);

        if (! is_string($json)) {
            $result = $this->run(['op' => 'manifest'], fn () => ['status' => 500, 'body' => '{}']);
            $json = json_encode($result->tools ?? []);
            Cache::put($cacheKey, $json, now()->addDays(30));
        }

        $tools = json_decode($json);

        return $this->tools = is_array($tools) ? $tools : [];
    }

    public function call(string $name, object $arguments, Closure $api): array
    {
        $result = $this->run(['op' => 'call', 'name' => $name, 'arguments' => $arguments], $api);

        $content = [];
        foreach ($result->content ?? [] as $item) {
            $content[] = json_decode(json_encode($item), true);
        }

        return ['content' => $content, 'isError' => (bool) ($result->isError ?? false)];
    }

    /**
     * Start the bridge, send one command and serve its API requests until it
     * prints the result.
     *
     * @param  array<string, mixed>  $command
     */
    private function run(array $command, Closure $api): object
    {
        $bridge = $this->bridgePath();
        $node = (string) config('connector.engine.node', 'node');
        $deadline = microtime(true) + max(5, (int) config('connector.engine.timeout', 150));

        $process = @proc_open(
            [$node, $bridge],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname($bridge),
            // Nothing of the application's environment (database passwords, keys) is passed on.
            [
                'PATH' => getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
                'NODE_ENV' => 'production',
                'NODE_NO_WARNINGS' => '1',
            ],
        );

        if (! is_resource($process)) {
            throw new RuntimeException('The connector tool engine could not be started (is Node.js installed?).');
        }

        [$stdin, $stdout, $stderr] = $pipes;
        stream_set_blocking($stdout, false);
        stream_set_blocking($stderr, false);

        $buffer = '';
        $errors = '';

        try {
            $this->writeLine($stdin, json_encode($command, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            while (true) {
                while (($newline = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $newline);
                    $buffer = substr($buffer, $newline + 1);
                    $message = $line === '' ? null : json_decode($line);

                    if (! is_object($message)) {
                        continue;
                    }

                    if (($message->type ?? null) === 'api') {
                        $this->writeLine($stdin, $this->apiResponseLine($message, $api));

                        continue;
                    }

                    if (($message->type ?? null) === 'result' && is_object($message->result ?? null)) {
                        return $message->result;
                    }

                    throw new RuntimeException('The connector tool engine failed: '.(is_string($message->message ?? null) ? $message->message : 'unknown error'));
                }

                $remaining = $deadline - microtime(true);

                if ($remaining <= 0) {
                    throw new RuntimeException('The tool did not finish in time.');
                }

                $read = [$stdout, $stderr];
                $write = $except = null;

                if (@stream_select($read, $write, $except, (int) min($remaining, 5), 0) === false) {
                    throw new RuntimeException('The connector tool engine stopped responding.');
                }

                $errors = substr($errors.(string) stream_get_contents($stderr), -4000);
                $chunk = stream_get_contents($stdout);

                if ($chunk !== false && $chunk !== '') {
                    $buffer .= $chunk;

                    continue;
                }

                if (feof($stdout)) {
                    throw new RuntimeException('The connector tool engine exited without a result.');
                }
            }
        } catch (RuntimeException $e) {
            Log::warning('Connector tool engine: '.$e->getMessage(), ['stderr' => trim($errors)]);

            throw $e;
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            $status = proc_get_status($process);
            if ($status['running'] ?? false) {
                proc_terminate($process, 9);
            }
            proc_close($process);
        }
    }

    /** Run the API request the bridge asked for and build the answer line. */
    private function apiResponseLine(object $message, Closure $api): string
    {
        $query = isset($message->params) && is_object($message->params)
            ? json_decode(json_encode($message->params), true)
            : null;

        $response = $api(
            (string) ($message->method ?? 'get'),
            (string) ($message->path ?? '/'),
            $query,
            $message->data ?? null,
        );

        // The API answers JSON; it is passed on as it is so that empty objects
        // and lists stay what they were. Anything else travels as a string.
        $body = $response['body'];
        $body = $body !== '' && json_validate($body) && ! str_contains($body, "\n")
            ? $body
            : json_encode($body === '' ? null : (json_decode($body) ?? $body), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return '{"type":"api_response","id":'.(int) ($message->id ?? 0).',"status":'.(int) $response['status'].',"body":'.$body.'}';
    }

    /** @param  resource  $stream */
    private function writeLine($stream, string $line): void
    {
        $line .= "\n";

        for ($written = 0, $length = strlen($line); $written < $length;) {
            $bytes = @fwrite($stream, substr($line, $written));

            if ($bytes === false || $bytes === 0) {
                throw new RuntimeException('The connector tool engine closed its input.');
            }

            $written += $bytes;
        }

        fflush($stream);
    }

    /** @throws RuntimeException when the engine is not installed */
    public function bridgePath(): string
    {
        $candidates = array_filter([
            config('connector.engine.bridge'),
            '/opt/netsendo-mcp/dist/bridge.js',          // the Docker image
            base_path('../mcp/dist/bridge.js'),          // a repository checkout
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('The connector tool engine (mcp/dist/bridge.js) is not installed.');
    }
}
