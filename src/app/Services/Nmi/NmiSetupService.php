<?php

namespace App\Services\Nmi;

use App\Models\DedicatedIpAddress;
use App\Models\DomainConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Setting up NetSendo MTA (NMI) for a domain: which dedicated IPs send for it, and whether
 * NMI is switched on with the SMTP login NmiProvider uses on the MTA.
 *
 * The callers check that the IP and the domain belong to the same user.
 */
class NmiSetupService
{
    /**
     * Assign an IP to a domain (or move it from another one). Writes both directions:
     * dedicated_ip_addresses.domain_configuration_id and, when the domain has none yet,
     * domain_configurations.dedicated_ip_id (its primary IP).
     *
     * DKIM keys stay: they are not tied to a domain, the record is published under
     * <selector>._domainkey.<domain> of the domain the IP now has.
     */
    public function assignIpToDomain(DedicatedIpAddress $ip, DomainConfiguration $domain): void
    {
        if ($ip->domain_configuration_id === $domain->id) {
            if (!$domain->dedicated_ip_id) {
                $domain->update(['dedicated_ip_id' => $ip->id]);
            }

            return;
        }

        $this->ensureNoMailboxSendsThrough($ip, 'domain_configuration_id');

        DB::transaction(function () use ($ip, $domain) {
            $previous = $ip->domainConfiguration;

            $ip->update(['domain_configuration_id' => $domain->id]);

            if ($previous) {
                $this->passOnPrimaryIp($previous, $ip);
            }

            if (!$domain->dedicated_ip_id) {
                $domain->update(['dedicated_ip_id' => $ip->id]);
            }
        });
    }

    /**
     * Take an IP away from its domain
     */
    public function unassignIp(DedicatedIpAddress $ip): void
    {
        $this->ensureNoMailboxSendsThrough($ip, 'domain_configuration_id');

        DB::transaction(function () use ($ip) {
            $previous = $ip->domainConfiguration;

            $ip->update(['domain_configuration_id' => null]);

            if ($previous) {
                $this->passOnPrimaryIp($previous, $ip);
            }
        });
    }

    /**
     * Switch NMI on for a verified domain. The SMTP login is generated the first time and
     * kept when NMI is switched off and on again.
     */
    public function enable(DomainConfiguration $domain): void
    {
        if (!config('nmi.features.enabled')) {
            throw ValidationException::withMessages([
                'domain' => __('nmi.installation_disabled'),
            ]);
        }

        if (!$domain->cname_verified) {
            throw ValidationException::withMessages([
                'domain' => __('nmi.domain_not_verified', ['domain' => $domain->domain]),
            ]);
        }

        if (!$domain->hasNmiCredentials()) {
            $domain->generateNmiCredentials();
        }

        $domain->nmi_enabled = true;
        $domain->nmi_enabled_at = now();
        $domain->save();
    }

    /**
     * Switch NMI off. Refused while a mailbox sends through it: switch those mailboxes
     * to another provider first.
     */
    public function disable(DomainConfiguration $domain): void
    {
        $count = $domain->nmiMailboxes()->count();

        if ($count > 0) {
            throw ValidationException::withMessages([
                'domain' => __('nmi.domain_in_use', ['count' => $count]),
            ]);
        }

        $domain->update(['nmi_enabled' => false]);
    }

    private function ensureNoMailboxSendsThrough(DedicatedIpAddress $ip, string $field): void
    {
        $count = $ip->mailboxes()->count();

        if ($count > 0) {
            throw ValidationException::withMessages([
                $field => __('nmi.ip_in_use', ['count' => $count]),
            ]);
        }
    }

    /**
     * When the primary IP of a domain leaves it, another IP of the domain takes its place
     */
    private function passOnPrimaryIp(DomainConfiguration $domain, DedicatedIpAddress $leaving): void
    {
        if ($domain->dedicated_ip_id !== $leaving->id) {
            return;
        }

        $next = $domain->dedicatedIps()
            ->whereKeyNot($leaving->id)
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->value('id');

        $domain->update(['dedicated_ip_id' => $next]);
    }
}
