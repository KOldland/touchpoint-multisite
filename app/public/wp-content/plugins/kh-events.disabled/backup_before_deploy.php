<?php
echo "💾 KH Events Pre-Deployment Backup Script\n";
echo "==========================================\n\n";

$backup_dir = 'backups/' . date('Y-m-d_H-i-s');

if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

echo "📁 Created backup directory: $backup_dir\n\n";

$config_files = array(
    'api_keys.json',
    'social_media_config_sample.json',
    'hubspot_config_sample.json',
    'webhook_config_sample.json'
);

foreach ($config_files as $file) {
    if (file_exists($file)) {
        copy($file, "$backup_dir/$file");
        echo "✅ Backed up: $file\n";
    }
}

echo "\n✅ Backup completed successfully!\n\n";
echo "⚠️  Keep this backup safe during deployment.\n\n";
echo "🚀 Ready for deployment!\n";
?>