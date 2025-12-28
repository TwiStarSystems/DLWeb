<?php
/**
 * Site Settings Management
 * Handles loading, saving, and caching of site settings
 */

require_once __DIR__ . '/config.php';

/**
 * Settings Class
 * Manages site-wide settings with caching
 */
class Settings {
    private static $instance = null;
    private $db;
    private $cache = [];
    private $loaded = false;
    
    private function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Settings();
        }
        return self::$instance;
    }
    
    /**
     * Load all settings into cache
     */
    private function loadAll() {
        if ($this->loaded) return;
        
        try {
            $stmt = $this->db->query("SELECT setting_key, setting_value, setting_type FROM settings");
            while ($row = $stmt->fetch()) {
                $this->cache[$row['setting_key']] = $this->castValue($row['setting_value'], $row['setting_type']);
            }
            $this->loaded = true;
        } catch (PDOException $e) {
            // Table might not exist yet
            $this->loaded = true;
        }
    }
    
    /**
     * Cast value based on setting type
     */
    private function castValue($value, $type) {
        switch ($type) {
            case 'number':
                return (int) $value;
            case 'boolean':
                return (bool) $value;
            case 'json':
                return json_decode($value, true) ?? [];
            default:
                return $value;
        }
    }
    
    /**
     * Get a setting value
     */
    public function get($key, $default = null) {
        $this->loadAll();
        return $this->cache[$key] ?? $default;
    }
    
    /**
     * Set a setting value
     */
    public function set($key, $value) {
        try {
            // Handle boolean conversion for storage
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif (is_array($value)) {
                $value = json_encode($value);
            }
            
            $stmt = $this->db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
            
            // Update cache
            $this->cache[$key] = $value;
            
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Get all settings for a category
     */
    public function getByCategory($category) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM settings 
                WHERE category = ? 
                ORDER BY display_order ASC
            ");
            $stmt->execute([$category]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get all categories
     */
    public function getCategories() {
        try {
            $stmt = $this->db->query("
                SELECT DISTINCT category FROM settings ORDER BY category ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Update multiple settings at once
     */
    public function updateBatch($settings) {
        $success = true;
        foreach ($settings as $key => $value) {
            if (!$this->set($key, $value)) {
                $success = false;
            }
        }
        return $success;
    }
    
    /**
     * Get CSS custom properties from color settings
     */
    public function getColorCSS() {
        $this->loadAll();
        
        $css = ":root {\n";
        
        $colorMap = [
            'color_bg_main' => '--bg-main',
            'color_bg_panel' => '--bg-panel',
            'color_primary' => '--primary',
            'color_secondary' => '--secondary',
            'color_accent' => '--accent',
            'color_danger' => '--danger',
            'color_text_main' => '--text-main',
            'color_text_muted' => '--text-muted',
        ];
        
        foreach ($colorMap as $settingKey => $cssVar) {
            $value = $this->get($settingKey);
            if ($value) {
                $css .= "    {$cssVar}: {$value};\n";
            }
        }
        
        $css .= "}\n";
        
        return $css;
    }
    
    /**
     * Process footer text with variables
     */
    public function getFooterText() {
        $text = $this->get('footer_text', '© {year} {site_title}. All rights reserved.');
        $text = str_replace('{year}', date('Y'), $text);
        $text = str_replace('{site_title}', $this->get('site_title', 'My Website'), $text);
        return $text;
    }
    
    /**
     * Clear the settings cache
     */
    public function clearCache() {
        $this->cache = [];
        $this->loaded = false;
    }
}

/**
 * Helper functions for templates
 */
function settings() {
    return Settings::getInstance();
}

function getSetting($key, $default = null) {
    return settings()->get($key, $default);
}

function siteTitle() {
    return settings()->get('site_title', SITE_TITLE);
}

function siteTagline() {
    return settings()->get('site_tagline', '');
}

function siteFavicon() {
    return settings()->get('site_favicon', '');
}

function footerText() {
    return settings()->getFooterText();
}

function colorCSS() {
    return settings()->getColorCSS();
}

function allowRegistration() {
    return (bool) settings()->get('allow_registration', true);
}
?>
