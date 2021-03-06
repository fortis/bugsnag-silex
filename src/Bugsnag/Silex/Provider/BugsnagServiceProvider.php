<?php

namespace Bugsnag\Silex\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;

class BugsnagServiceProvider implements ServiceProviderInterface
{
    private static $request;

    public function register(Application $app)
    {
        $app['bugsnag'] = $app->share(function () use($app) {
            $client = new \Bugsnag_Client($app['bugsnag.options']['apiKey']);
            $client->setNotifier(array(
                'name' => 'Bugsnag Silex',
                'version' => '1.1.0',
                'url' => 'https://github.com/bugsnag/bugsnag-silex',
            ));
            set_error_handler(array($client, 'errorhandler'));
            set_exception_handler(array($client, 'exceptionhandler'));
            return $client;
        });

        /* Captures the request's information */
        $app->before(function ($request) {
            self::$request = $request;
        });

        $app->error(function (\Exception $error, $code) use($app) {
            $app['bugsnag']->setBeforeNotifyFunction($this->filterFramesFunc());

            if (self::$request) {
                $session = self::$request->getSession();
                if ($session) {
                    $session = $session->all();
                }

                $qs = array();
                parse_str(self::$request->getQueryString(), $qs);

                $params = array(
                    "request" => array(
                        "params" => $qs,
                        "requestFormat" => self::$request->getRequestFormat(),
                    )
                );

                if ($session) {
                    $params["session"] = $session;
                }

                $cookies = self::$request->cookies->all();
                if ($cookies) {
                    $params["cookies"] = $cookies;
                }

                $app['bugsnag']->notifyException($error, $params);
            }
        });
    }

    public function boot(Application $app)
    {
        //
    }

    private function filterFramesFunc()
    {
        return function (\Bugsnag_Error $error) {
            foreach ($error->stacktrace->frames as $key => $frame) {
                if (!preg_match('/^\[internal\]|\/vendor\//', $frame['file'])) {
                    $error->stacktrace->frames[$key]['inProject'] = TRUE;
                }
            }
        };
    }
}
