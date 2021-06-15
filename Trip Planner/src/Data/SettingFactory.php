<?php

namespace Gibbon\Module\TripPlanner\Data;

/**
 * Settings Factory to easily create settings.
 */
class SettingFactory
{
    protected $settings;

    public function __construct() {
        $this->settings = [];
    }

    /**
     * Add a new Setting.
     * @param Setting name.
     * @return new Setting object.
     */
    public function addSetting($name) {
        $setting = new Setting($name);
        $this->settings[] = $setting;
        return $setting;
    }

    /**
     * Get Settings.
     * @return Array of Settings.
     */
    public function getSettings() {
        return $this->settings;
    }
}