<?php

namespace Yab\Quarx\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Yab\Quarx\Facades\CryptoServiceFacade;
use Yab\Quarx\Repositories\MenuRepository;
use Yab\Quarx\Repositories\LinksRepository;
use Yab\Quarx\Repositories\PagesRepository;
use Yab\Quarx\Repositories\WidgetsRepository;
use Yab\Quarx\Interfaces\QuarxServiceInterface;

class QuarxService implements QuarxServiceInterface
{
    /**
     * Generates a notification for the app
     * @param  string $string Notification string
     * @param  string $type   Notification type
     * @return void
     */
    public function notification($string, $type = null)
    {
        if (is_null($type)) {
            $type = 'info';
        }

        Session::flash("notification", $string);
        Session::flash("notificationType", 'alert-'.$type);
    }

    /**
     * Get a module's asset
     * @param  string $module      Module name
     * @param  string $path        Path to module asset
     * @param  string $contentType Asset type
     * @return string
     */
    public function asset($path, $contentType = 'null', $fullURL = true)
    {

        if ( ! $fullURL) {
            return base_path(__DIR__.'/../Assets/'.$path);
        }

        return url('quarx/asset/'.CryptoServiceFacade::encrypt($path).'/'.CryptoServiceFacade::encrypt($contentType));
    }

    /**
     * Module Assets
     *
     * @param  string $module      Module name
     * @param  string $path        Asset path
     * @param  string $contentType Content type
     * @return string
     */
    public function moduleAsset($module, $path, $contentType = 'null')
    {
        $path = base_path(Config::get('quarx.module-directory').'/'.ucfirst($module).'/Assets/'.$path);
        return url('quarx/asset/'.CryptoServiceFacade::encrypt($path).'/'.CryptoServiceFacade::encrypt($contentType).'/?isModule=true');
    }

    /**
     * Module Config
     *
     * @param  string $module      Module name
     * @param  string $path        Asset path
     * @param  string $contentType Content type
     * @return string
     */
    public function moduleConfig($module, $path)
    {
        $configArray = @include(base_path(Config::get('quarx.module-directory').'/'.ucfirst($module).'/config.php'));
        return QuarxService::assignArrayByPath($configArray, $path);
    }

    /**
     * Creates a breadcrumb trail
     * @param  array $locations Locations array
     * @return string
     */
    public function breadcrumbs($locations)
    {
        $trail = '';

        foreach ($locations as $location) {
            if (is_array($location)) {
                foreach ($location as $key => $value) {
                    $trail .= '<li><a href="'.$value.'">'.ucfirst($key).'</a></li>';
                }
            } else {
                $trail .= '<li>'.ucfirst($location).'</li>';
            }
        }

        return $trail;
    }

    /**
     * Get Module Config
     *
     * @param  string $key Config key
     * @return mixed
     */
    public function config($key)
    {
        $splitKey = explode('.', $key);

        $moduleConfig = include(__DIR__.'/../PublishedAssets/Config/'.$splitKey[0].'.php');

        $strippedKey = preg_replace('/'.$splitKey[1].'./', '', preg_replace('/'.$splitKey[0].'./', '', $key, 1), 1);

        return $moduleConfig[$strippedKey];
    }

    /**
     * Assign a value to the path
     * @param  array &$arr  Original Array of values
     * @param  string $path  Array as path string
     * @return mixed
     */
    public function assignArrayByPath(&$arr, $path)
    {
        $keys = explode('.', $path);

        while ($key = array_shift($keys)) {
            $arr = &$arr[$key];
        }

        return $arr;
    }

    /**
     * Convert a string to a URL
     * @param  string $string
     * @return string
     */
    public function convertToURL($string)
    {
        return preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', strtolower($string)));
    }

    /**
     * Get a widget
     * @param  string $uuid
     * @return widget
     */
    public function widget($uuid)
    {
        $widget = WidgetsRepository::getWidgetByUUID($uuid);

        if (Gate::allows('quarx', Auth::user())) {
            $widget->content .= '<a href="'. url('quarx/widgets/'.CryptoServiceFacade::encrypt($widget->id).'/edit') .'" class="btn btn-default"><span class="fa fa-edit"></span> Edit</a>';
        }

        return $widget->content;
    }

    /**
     * Add these views to the packages
     *
     * @param string $dir
     */
    public function addToPackages($dir)
    {
        $files = glob($dir.'/*');

        $packageViews = Config::get('quarx.package-menus');

        if (is_null($packageViews)) {
            $packageViews = [];
        }

        foreach ($files as $view) {
            array_push($packageViews, $view);
        }

        return Config::set('quarx.package-menus', $packageViews);
    }

    /**
     * Quarx package Menus
     * @return string
     */
    public function packageMenus()
    {
        $packageMenus = '';
        $packageViews = Config::get('quarx.package-menus', []);

        foreach ($packageViews as $view) {
            include($view);
        }
    }

    /**
     * Get a view
     * @param  string $uuid
     * @param  View $view
     * @return string
     */
    public function menu($uuid, $view = null)
    {
        $pageRepo = new PagesRepository;
        $menu = MenuRepository::getMenuByUUID($uuid)->first();

        if (! $menu) {
            return '';
        }

        $links = LinksRepository::getLinksByMenuID($menu->id);
        $response = '';

        foreach ($links as $link) {
            if ($link->external) {
                $response .= "<a href=\"$link->external_url\">$link->name</a>";
            } else {
                $page = $pageRepo->findPagesById($link->page_id);
                $response .= "<a href=\"".URL::to('page/'.$page->url)."\">$link->name</a>";
            }
        }

        if (! is_null($view)) {
            $response = view($view, ['links' => $links, 'linksAsHtml' => $response]);
        }

        if (Gate::allows('quarx', Auth::user())) {
            $response .= '<a href="'. url('quarx/menus/'.CryptoServiceFacade::encrypt($menu->id).'/edit') .'" class="btn btn-default"><span class="fa fa-edit"></span> Edit</a>';
        }

        return $response;
    }

    public function editBtn($type = null, $id = null)
    {
        if (Gate::allows('quarx', Auth::user())) {
            if (! is_null($id)) {
                return '<a href="'. url('quarx/'.$type.'/'.CryptoServiceFacade::encrypt($id).'/edit') .'" class="btn btn-default pull-right"><span class="fa fa-edit"></span> Edit</a>';
            } else {
                return '<a href="'. url('quarx/'.$type) .'" class="btn btn-default pull-right"><span class="fa fa-edit"></span> Edit</a>';
            }
        }

        return '';
    }
}