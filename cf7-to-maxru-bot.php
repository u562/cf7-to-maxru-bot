<?php
/**
 * Plugin Name: CF7 to Max.ru Bot
 * Plugin URI: https://orenpro.ru/
 * Description: Отправляет заявки из Contact Form 7 в канал мессенджера Max.ru через бота
 * Version: 1.0
 * Author: orenpro
 */

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

// Добавляем страницу настроек в админку
add_action('admin_menu', 'cf7_maxru_add_admin_menu');
add_action('admin_init', 'cf7_maxru_settings_init');

function cf7_maxru_add_admin_menu() {
    add_options_page(
        'CF7 to Max.ru Bot',
        'CF7 to Max.ru Bot',
        'manage_options',
        'cf7-maxru-bot',
        'cf7_maxru_options_page'
    );
}

function cf7_maxru_settings_init() {
    register_setting('cf7MaxruPlugin', 'cf7_maxru_settings');

    add_settings_section(
        'cf7_maxru_plugin_section',
        'Настройки подключения к Max.ru Bot',
        'cf7_maxru_settings_section_callback',
        'cf7MaxruPlugin'
    );

    add_settings_field(
        'cf7_maxru_bot_token',
        'Токен бота',
        'cf7_maxru_bot_token_render',
        'cf7MaxruPlugin',
        'cf7_maxru_plugin_section'
    );

    add_settings_field(
        'cf7_maxru_chat_id',
        'Chat ID',
        'cf7_maxru_chat_id_render',
        'cf7MaxruPlugin',
        'cf7_maxru_plugin_section'
    );

    add_settings_field(
        'cf7_maxru_enable_notifications',
        'Включить уведомления',
        'cf7_maxru_enable_notifications_render',
        'cf7MaxruPlugin',
        'cf7_maxru_plugin_section'
    );
}

function cf7_maxru_bot_token_render() {
    $options = get_option('cf7_maxru_settings');
    ?>
    <input type="text" name="cf7_maxru_settings[cf7_maxru_bot_token]" 
           value="<?php echo isset($options['cf7_maxru_bot_token']) ? esc_attr($options['cf7_maxru_bot_token']) : ''; ?>" 
           size="50" />
    <?php
}

function cf7_maxru_chat_id_render() {
    $options = get_option('cf7_maxru_settings');
    ?>
    <input type="text" name="cf7_maxru_settings[cf7_maxru_chat_id]" 
           value="<?php echo isset($options['cf7_maxru_chat_id']) ? esc_attr($options['cf7_maxru_chat_id']) : ''; ?>" 
           size="20" />
    <p class="description">Введите ID канала/чата (обычно отрицательное число)</p>
    <?php
}

function cf7_maxru_enable_notifications_render() {
    $options = get_option('cf7_maxru_settings');
    ?>
    <input type="checkbox" name="cf7_maxru_settings[cf7_maxru_enable_notifications]" 
           value="1" <?php checked(1, isset($options['cf7_maxru_enable_notifications']) ? $options['cf7_maxru_enable_notifications'] : 0); ?> />
    <label>Отправлять уведомления о новых заявках</label>
    <?php
}

function cf7_maxru_settings_section_callback() {
    echo '<p>Введите настройки вашего бота в Max.ru</p>';
}

function cf7_maxru_options_page() {
    ?>
    <div class="wrap">
        <h1>CF7 to Max.ru Bot Settings</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('cf7MaxruPlugin');
            do_settings_sections('cf7MaxruPlugin');
            submit_button();
            ?>
        </form>
        
        <hr>
        <h2>Тест отправки сообщения</h2>
        <form method="post" action="">
            <?php wp_nonce_field('cf7_maxru_test_nonce', 'cf7_maxru_test_nonce_field'); ?>
            <input type="submit" name="cf7_maxru_test" class="button button-primary" value="Отправить тестовое сообщение">
        </form>
        <?php
        // Обработка тестовой отправки
        if (isset($_POST['cf7_maxru_test']) && check_admin_referer('cf7_maxru_test_nonce', 'cf7_maxru_test_nonce_field')) {
            $result = cf7_maxru_send_test_message();
            echo $result;
        }
        ?>
    </div>
    <?php
}

// Функция отправки сообщения в Max.ru
function cf7_maxru_send_message($message_text) {
    $options = get_option('cf7_maxru_settings');
    
    if (!isset($options['cf7_maxru_enable_notifications']) || $options['cf7_maxru_enable_notifications'] != 1) {
        return array('success' => false, 'message' => 'Уведомления отключены в настройках');
    }
    
    $botToken = $options['cf7_maxru_bot_token'];
    $chatId = (int)$options['cf7_maxru_chat_id'];
    
    if (empty($botToken) || empty($chatId)) {
        return array('success' => false, 'message' => 'Не указаны токен бота или Chat ID');
    }
    
    $url = "https://platform-api.max.ru/messages?chat_id=" . $chatId;
    
    $ch = curl_init($url);
    
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            "Authorization: $botToken",
            "Content-Type: application/json"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'text' => $message_text,
            'format' => 'markdown'
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false, // Отключаем проверку SSL
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return array('success' => false, 'message' => 'cURL ошибка: ' . $error);
    }
    
    if ($httpCode == 200) {
        return array('success' => true, 'message' => 'Сообщение успешно отправлено');
    } else {
        return array('success' => false, 'message' => 'HTTP ошибка: ' . $httpCode . ' Ответ: ' . $response);
    }
}

// Функция для тестовой отправки
function cf7_maxru_send_test_message() {
    $testMessage = '🤖 Тест: сообщение от плагина CF7 to Max.ru Bot успешно отправлено! ' . date('Y-m-d H:i:s');
    
    $result = cf7_maxru_send_message($testMessage);
    
    if ($result['success']) {
        return '<div class="notice notice-success"><p>✅ Тестовое сообщение успешно отправлено!</p></div>';
    } else {
        return '<div class="notice notice-error"><p>❌ Ошибка отправки: ' . esc_html($result['message']) . '</p></div>';
    }
}

// Хук для отправки сообщения при успешной отправке формы CF7
add_action('wpcf7_mail_sent', 'cf7_maxru_handle_form_submission');

function cf7_maxru_handle_form_submission($contact_form) {
    $submission = WPCF7_Submission::get_instance();
    
    if ($submission) {
        $posted_data = $submission->get_posted_data();
        $form_title = $contact_form->title();
        
        // Формируем сообщение
        $message = "📝 *Новая заявка с сайта*\n";
        $message .= "📋 *Форма:* " . $form_title . "\n";
        $message .= "🕒 *Время:* " . current_time('Y-m-d H:i:s') . "\n\n";
        $message .= "*Данные заявки:*\n";
        
        foreach ($posted_data as $key => $value) {
            // Пропускаем служебные поля CF7
            if (strpos($key, '_wpcf7') === 0) {
                continue;
            }
            
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            
            if (!empty($value)) {
                $message .= "• *" . ucfirst(str_replace(['-', '_'], ' ', $key)) . ":* " . $value . "\n";
            }
        }
        
        // Отправляем сообщение
        cf7_maxru_send_message($message);
    }
}

// Добавляем ссылку на настройки в список плагинов
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'cf7_maxru_add_settings_link');

function cf7_maxru_add_settings_link($links) {
    $settings_link = '<a href="options-general.php?page=cf7-maxru-bot">Настройки</a>';
    array_unshift($links, $settings_link);
    return $links;
}
?>
