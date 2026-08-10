<?php

declare(strict_types=1);

namespace IasdCentral\Repositories;

final class SettingsRepository extends BaseRepository
{
    private const DEFAULTS = [
        'font_family' => 'Arial',
        'layout_density' => 'comfortable',
        'language' => 'pt-BR',
        'brand_color' => '#7a1f38',
        'sidebar_color' => '#251b20',
        'sidebar_width' => '250',
        'theme_mode' => 'light',
        'sinapi_auto_update' => '1',
        'sinapi_last_check' => '',
        'sinapi_last_status' => 'Nunca verificado',
        'sinapi_last_error' => '',
    ];

    public function all(): array
    {
        $rows = $this->fetchAll('SELECT setting_key, setting_value FROM app_settings');
        $settings = self::DEFAULTS;
        foreach ($rows as $row) $settings[$row['setting_key']] = (string) $row['setting_value'];
        return $settings;
    }

    public function update(array $settings, int $userId): void
    {
        $allowed = array_intersect_key($settings, self::DEFAULTS);
        $statement = $this->db()->prepare(
            'INSERT INTO app_settings (setting_key, setting_value, updated_by, updated_at) VALUES (:setting_key, :setting_value, :updated_by, NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_by=VALUES(updated_by), updated_at=NOW()'
        );
        foreach ($allowed as $key => $value) {
            $statement->execute(['setting_key' => $key, 'setting_value' => (string) $value, 'updated_by' => $userId]);
        }
    }

    public function updateSystem(array $settings): void
    {
        $allowed = array_intersect_key($settings, self::DEFAULTS);
        $statement = $this->db()->prepare(
            'INSERT INTO app_settings (setting_key, setting_value, updated_by, updated_at) VALUES (:setting_key, :setting_value, NULL, NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_by=NULL, updated_at=NOW()'
        );
        foreach ($allowed as $key => $value) {
            $statement->execute(['setting_key' => $key, 'setting_value' => (string) $value]);
        }
    }
}
