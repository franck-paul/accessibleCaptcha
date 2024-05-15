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
    '2.1',
    [
        'requires'    => [['core', '2.30']],
        'permissions' => 'My',
        'priority'    => 200,
        'type'        => 'plugin',
    ]
);
