<?php

namespace App\Services\Nmi;

use App\Models\DedicatedIpAddress;
use App\Models\DomainConfiguration;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The steps to a working own sending server, each with its live state, for the guide on
 * the Mail Infrastructure dashboard. Statuses: ok, todo (not done yet), warning (works, but
 * not as it should), error (blocks sending), unknown (could not be checked), info.
 *
 * The texts are in the panel's language files (nmi.guide.steps.<key>); the data carries
 * what they need (names, addresses, records to set).
 */
class SetupGuide
{
    public function __construct(
        private MtaProbe $probe,
        private OutboundPortCheck $portCheck,
        private MailServerName $names,
        private DkimKeyManager $dkim,
    ) {
    }

    /**
     * @return list<array{key: string, status: string, data: array}>
     */
    public function steps(User $user): array
    {
        $mta = $this->probe->probe();
        $serverAddresses = $mta['addresses'];
        $port25 = $this->portCheck->port25Open();
        $server = $this->names->check();

        $domains = DomainConfiguration::forUser($user->id)->orderBy('domain')->get();
        $nmiDomains = $domains->filter(fn (DomainConfiguration $domain) => $domain->cname_verified && $domain->nmi_enabled);
        $ips = DedicatedIpAddress::ownedBy($user->id)
            ->whereIn('domain_configuration_id', $nmiDomains->pluck('id'))
            ->with('domainConfiguration')
            ->orderBy('id')
            ->get();
        $mailboxes = Mailbox::where('user_id', $user->id)->where('provider', 'nmi')->where('is_active', true)->count();

        return [
            $this->step('switch', NmiSwitch::enabled() ? 'ok' : 'todo', [
                'forced_by_env' => NmiSwitch::forcedByEnv(),
                'can_toggle' => $user->isAdmin(),
            ]),
            $this->step('mta', $mta['online'] ? 'ok' : 'error', [
                'host' => MtaEndpoint::host(),
                'port' => MtaEndpoint::port(),
            ]),
            $this->step('port25', match ($port25) {
                true => 'ok',
                false => 'error',
                default => 'unknown',
            }),
            $this->step('server_name', $server['name'] === null ? 'error' : ($server['ok'] ? 'ok' : 'warning'), $server),
            $this->step('domain', $nmiDomains->isNotEmpty() ? 'ok' : 'todo', [
                'domains' => $domains->map(fn (DomainConfiguration $domain) => [
                    'id' => $domain->id,
                    'domain' => $domain->domain,
                    'verified' => (bool) $domain->cname_verified,
                    'nmi_enabled' => (bool) $domain->nmi_enabled,
                ])->values()->all(),
            ]),
            $this->step('ip', $ips->isNotEmpty() ? 'ok' : 'todo', [
                // The server's public addresses: the main one can be the first "dedicated" IP
                'server_addresses' => $this->publicAddresses($serverAddresses),
                'ips' => $this->ipList($ips),
            ]),
            $this->dkimStep($ips),
            $this->onServerStep($ips, $serverAddresses),
            $this->ptrStep($ips),
            $this->step('mailbox', $mailboxes > 0 ? 'ok' : 'todo', ['count' => $mailboxes]),
            $this->step('test', 'info'),
        ];
    }

    private function step(string $key, string $status, array $data = []): array
    {
        return ['key' => $key, 'status' => $status, 'data' => $data];
    }

    private function ipList(Collection $ips, ?callable $extra = null): array
    {
        return $ips->map(fn (DedicatedIpAddress $ip) => array_merge([
            'id' => $ip->id,
            'ip_address' => $ip->ip_address,
            'hostname' => $ip->hostname,
            'domain' => $ip->domainConfiguration?->domain,
            'provider' => $ip->provider,
        ], $extra ? $extra($ip) : []))->values()->all();
    }

    /**
     * DKIM keys generated and the record published
     */
    private function dkimStep(Collection $ips): array
    {
        if ($ips->isEmpty()) {
            return $this->step('dkim', 'todo', ['ips' => []]);
        }

        $states = [];
        $list = $this->ipList($ips, function (DedicatedIpAddress $ip) use (&$states) {
            $state = !$ip->hasDkimKeys() ? 'no_keys' : ($this->dkim->verifyDkimRecord($ip) ? 'published' : 'not_published');
            $states[] = $state;

            return ['dkim' => $state];
        });

        $status = in_array('no_keys', $states, true) ? 'todo' : (in_array('not_published', $states, true) ? 'warning' : 'ok');

        return $this->step('dkim', $status, ['ips' => $list]);
    }

    /**
     * The IPs are configured on the server, so the MTA sends from them
     */
    private function onServerStep(Collection $ips, ?array $serverAddresses): array
    {
        if ($ips->isEmpty()) {
            return $this->step('ip_on_server', 'todo', ['ips' => []]);
        }

        $list = $this->ipList($ips, fn (DedicatedIpAddress $ip) => [
            'on_server' => $serverAddresses === null
                ? null
                : in_array(MailServerName::normalizeIp((string) $ip->ip_address), $serverAddresses, true),
            'ipv6' => str_contains((string) $ip->ip_address, ':'),
        ]);

        $status = match (true) {
            $serverAddresses === null => 'unknown',
            collect($list)->every(fn (array $ip) => $ip['on_server'] && !$ip['ipv6']) => 'ok',
            default => 'warning',
        };

        return $this->step('ip_on_server', $status, ['ips' => $list]);
    }

    /**
     * Each IP and its name point at each other (PTR record at the provider, A record in DNS)
     */
    private function ptrStep(Collection $ips): array
    {
        if ($ips->isEmpty()) {
            return $this->step('ptr', 'todo', ['ips' => []]);
        }

        $list = $this->ipList($ips, function (DedicatedIpAddress $ip) {
            $check = $this->names->checkIp($ip);

            return ['name' => $check['name'], 'ptr' => $check['ptr'], 'ok' => $check['ok']];
        });

        return $this->step('ptr', collect($list)->every(fn (array $ip) => $ip['ok']) ? 'ok' : 'warning', ['ips' => $list]);
    }

    /**
     * @return list<string>
     */
    private function publicAddresses(?array $addresses): array
    {
        return array_values(array_filter(
            $addresses ?? [],
            fn (string $address) => filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
                && !str_starts_with($address, 'fe80:')
        ));
    }
}
