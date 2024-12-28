<?php

/**
 * @brief accessibleCaptcha, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Franck Paul and contributors
 *
 * @copyright Franck Paul carnet.franck.paul@gmail.com
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

namespace Dotclear\Plugin\accessibleCaptcha;

use Dotclear\App;
use Dotclear\Core\Process;
use Dotclear\Plugin\antispam\Antispam;

class Frontend extends Process
{
    public static function init(): bool
    {
        return self::status(My::checkContext(My::FRONTEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        // Check if filter is enabled
        Antispam::initFilters();

        $components = explode('\\', AntispamFilterAccessibleCaptcha::class);
        $id         = array_pop($components);
        $fs         = Antispam::$filters->getFilters();

        if (isset($fs[$id]) && $fs[$id]->active) {
            App::behavior()->addBehaviors([
                'publicCommentFormAfterContent' => FrontendBehaviors::publicCommentFormAfterContent(...),
                'publicHeadContent'             => FrontendBehaviors::publicHeadContent(...),
            ]);
        }

        return true;
    }
}
