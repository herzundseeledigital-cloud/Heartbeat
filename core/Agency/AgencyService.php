<?php
declare(strict_types=1);

namespace Heartbeat\Agency;

use Heartbeat\Bootstrap;
use Heartbeat\Database\Database;
use Heartbeat\Security\Security;

/**
 * Agency Mode, White-Labeling & Client-Guard Service
 */
final class AgencyService
{
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Check if White-Labeling is enabled
     */
    public function isWhiteLabel(): bool
    {
        $envVal = Bootstrap::config('agency.white_label');
        if ($envVal !== null) {
            return (bool)$envVal;
        }

        try {
            $db = Database::getInstance();
            $val = $db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'agency_mode'");
            return (bool)$val;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Retrieve Agency Name (e.g., 'Herz & Seele Digital')
     */
    public function getAgencyName(): string
    {
        if (!$this->isWhiteLabel()) {
            return (string)Bootstrap::config('app.name', 'Heartbeat CMS');
        }

        $envName = Bootstrap::config('agency.name');
        if (!empty($envName)) {
            return (string)$envName;
        }

        try {
            $db = Database::getInstance();
            $val = $db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'agency_name'");
            if (!empty($val)) {
                return (string)$val;
            }
        } catch (\Throwable) {
        }

        return 'Herz & Seele Digital';
    }

    /**
     * Retrieve Agency Support Email
     */
    public function getSupportEmail(): string
    {
        $envMail = Bootstrap::config('agency.support_email');
        if (!empty($envMail)) {
            return (string)$envMail;
        }

        try {
            $db = Database::getInstance();
            $val = $db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'agency_support_email'");
            if (!empty($val)) {
                return (string)$val;
            }
        } catch (\Throwable) {
        }

        return 'hallo@herzundseele-digital.de';
    }

    /**
     * Check if Client-Guard is active
     */
    public function isClientGuardActive(): bool
    {
        $envGuard = Bootstrap::config('agency.client_guard');
        if ($envGuard !== null) {
            return (bool)$envGuard;
        }

        try {
            $db = Database::getInstance();
            $val = $db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'client_guard'");
            return (bool)$val;
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Save Agency settings to options table
     */
    public function saveSettings(array $settings): void
    {
        $db = Database::getInstance();
        $fields = [
            'agency_mode'          => !empty($settings['white_label']) ? '1' : '0',
            'agency_name'          => trim((string)($settings['agency_name'] ?? 'Herz & Seele Digital')),
            'agency_support_email' => trim((string)($settings['support_email'] ?? 'hallo@herzundseele-digital.de')),
            'client_guard'         => !empty($settings['client_guard']) ? '1' : '0',
        ];

        foreach ($fields as $key => $value) {
            $existing = $db->fetchOne("SELECT id FROM {prefix}options WHERE option_name = :key", ['key' => $key]);
            if ($existing) {
                $db->update('{prefix}options', ['option_value' => $value], 'option_name = :key', ['key' => $key]);
            } else {
                $db->insert('{prefix}options', [
                    'option_name'  => $key,
                    'option_value' => $value,
                    'autoload'     => 1,
                ]);
            }
        }
    }

    /**
     * Render the Agency Support Dashboard Widget
     */
    public function renderSupportWidget(): string
    {
        $agencyName = Security::e($this->getAgencyName());
        $supportEmail = Security::e($this->getSupportEmail());
        $guardActive = $this->isClientGuardActive();

        $guardBadge = $guardActive
            ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Client-Guard: Aktiv</span>'
            : '<span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">Client-Guard: Deaktiviert</span>';

        return <<<HTML
<div class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 relative overflow-hidden">
    <div class="flex items-start justify-between mb-4">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#fb326f] to-[#e01e5a] flex items-center justify-center text-white font-bold text-sm shadow-lg shadow-[#fb326f]/20">
                HS
            </div>
            <div>
                <h3 class="text-sm font-bold text-white tracking-tight">{$agencyName}</h3>
                <p class="text-xs text-slate-400">Technischer Support</p>
            </div>
        </div>
        {$guardBadge}
    </div>
    
    <div class="flex flex-wrap items-center gap-3">
        <a href="mailto:{$supportEmail}?subject=Support-Anfrage%20Website" 
           class="btn-primary text-xs">
            <span class="material-symbols-outlined text-[16px]">mail</span>
            <span>Support kontaktieren</span>
        </a>
        <span class="text-xs text-slate-500 font-mono">{$supportEmail}</span>
    </div>
</div>
HTML;
    }
}

