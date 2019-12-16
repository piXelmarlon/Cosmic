<?php
namespace Core;

use App\Config;
use App\Flash;
use App\Models\Admin;
use App\Models\Core;

use App\Models\Permission;
use App\Models\Player;

use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Extensions\DateExtension;
use Twig\Loader\FilesystemLoader;

use Exception;

class View
{
    public static $cache;

    public static function render($view, $args = [])
    {
        extract($args, EXTR_SKIP);

        $file = dirname(__DIR__) . '/'.Config::view.'/'.$view;

        if (is_readable($file)) {
            require $file;
        } else {
            throw new Exception("$file not found");
        }
    }

    public static function renderTemplate($template, $args = [], $cacheTime = false)
    {
        echo static::getTemplate($template, $args, $cacheTime);
    }

    public static function getResponse($template, $args) {
        $args['load'] = true;
        echo json_encode(array(
            "id"            => $args['page'],
            "title"         => (!empty($args['title']) ? $args['title'] . ' - ' . Config::siteName : null),
            "content"       => self::getTemplate($template, $args, null, true),
            "replacepage"   => null
        ));
    }

    public static function getTemplate($template, $args = [], $cacheTime, $request = false)
    {
        static $twig = null;

        if ($twig === null) {
            $loader = new FilesystemLoader(dirname(__DIR__) . '/' . Config::view);
            $twig = new Environment($loader, array(
                'debug' => true
            ));

            $twig->addExtension(new DebugExtension());
            $twig->addExtension(new DateExtension());
            $twig->addExtension(new \Library\Bbcode(new \ChrisKonnertz\BBCode\BBCode()));

            $twig->addGlobal('path', Config::path);
            $twig->addGlobal('cpath', Config::imgPath);
            $twig->addGlobal('fpath', Config::figurePath);

            $twig->addGlobal('domain', Config::domain);

            $twig->addGlobal('shortname', Config::shortName);
            $twig->addGlobal('sitename', Config::siteName);

            $twig->addGlobal('publicKey', Config::publicKey);

            $twig->addGlobal('revision', Core::getWebsiteConfig('revision'));

            if (request()->player !== null) {
                $twig->addGlobal('player', request()->player);
                $twig->addGlobal('currencys', Player::getCurrencys(request()->player->id));
 
                //$twig->addGlobal('online_count', Core::getOnlineCount());

                // For all users with housekeeping access
                if (request()->player->rank >= Config::minRank) {
                    //$twig->addGlobal('staff_count', Admin::getStaffOnlineCount());
                    //$twig->addGlobal('hotel_ticketcount', Admin::getHotelTicketCount());
                    //$twig->addGlobal('helpticket_count', Admin::getHelpTicketCount());

                    $twig->addGlobal('alert_messages', Admin::getAlertMessages());
                    $twig->addGlobal('ban_messages', Admin::getBanMessages());
                    $twig->addGlobal('ban_times', Admin::getBanTime(request()->player->rank));

                    //$twig->addGlobal('flash_messages', Flash::getMessages());
                    $twig->addGlobal('player_permissions', Permission::get(request()->player->rank));
                    $twig->addGlobal('player_rank', Player::getHotelRank(request()->player->rank));
                }
            }
        }

        if(Config::cacheEnabled && static::$cache === null) { 
            \App\Middleware\CacheMiddleware::set($template, $args, $cacheTime);
        }

        if(request()->isAjax() && $request == false) {
            self::getResponse($template, $args);
        } else {
            return $twig->render($template, $args);
        }
    }
}