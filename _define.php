<?php

/**
 * @brief Accessible Captcha, an antispam filter plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Julien Wajsberg and contributors
 *
 * @copyright Julien Wajsberg and contributors
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
$this->registerModule(
    'Accessible Captcha',
    'This is an accessible captcha',
    'Julien Wajsberg',
    '3.0',
    [
        'date'        => '2025-05-03T05:47:44+0200',
        'requires'    => [['core', '2.34']],
        'permissions' => 'My',
        'priority'    => 200,
        'type'        => 'plugin',

        'details'    => 'https://open-time.net/?q=accessibleCaptcha',
        'support'    => 'https://github.com/franck-paul/accessibleCaptcha',
        'repository' => 'https://raw.githubusercontent.com/franck-paul/accessibleCaptcha/main/dcstore.xml',
        'license'    => 'gpl2',
    ]
);
