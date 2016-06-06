<?php
/**
 * Created by PhpStorm.
 * User: janhuang
 * Date: 15/6/24
 * Time: 上午11:25
 * Github: https://www.github.com/janhuang
 * Coding: https://www.coding.net/janhuang
 * SegmentFault: http://segmentfault.com/u/janhuang
 * Blog: http://segmentfault.com/blog/janhuang
 * Gmail: bboyjanhuang@gmail.com
 * WebSite: http://www.janhuang.me
 */

namespace FastD\Debug;

use FastD\Debug\Exceptions\HttpException;
use FastD\Debug\Theme\Theme;
use Throwable;

/**
 * Class Wrapper
 *
 * @package FastD\Debug
 */
class Wrapper
{
    /**
     * @var Debug
     */
    protected $handler;

    /**
     * @var Throwable
     */
    protected $throwable;

    /**
     * @var Theme
     */
    protected $style;

    /**
     * @var bool
     */
    protected $cli;

    /**
     * Wrapper constructor.
     * @param Debug $debug
     * @param Throwable $throwable
     */
    public function __construct(Debug $debug, Throwable $throwable)
    {
        $this->handler = $debug;

        $this->throwable = $throwable;

        $theme = $debug->getTheme();

        $this->style = new $theme($throwable);

        $this->cli = 'cli' === PHP_SAPI ? true : false;

        unset($theme);
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->throwable instanceof HttpException
            ? $this->throwable->getStatusCode()
            : $this->throwable->getCode();
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        $headers = [
            'Content-Type' => 'text/html;'
        ];

        if ($this->throwable instanceof HttpException && !empty($this->throwable->getHeaders())) {
            $headers = $this->throwable->getHeaders();
        }

        return $headers;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        switch ($this->getStatusCode()) {
            case 404:
                $title = 'Sorry, the page you are looking for could not be found.';
                break;
            default:
                $title = 'Whoops, looks like something went wrong.';
        }

        return $title;
    }

    /**
     * @return Throwable
     */
    public function getThrowable()
    {
        return $this->throwable;
    }

    /**
     * @return Theme
     */
    public function getStyleSheet()
    {
        return $this->style;
    }

    /**
     * @return bool
     */
    public function isCli()
    {
        return $this->cli;
    }

    /**
     * @param int
     * @return int
     */
    protected function filterStatusCode()
    {
        $statusCode = $this->getStatusCode();

        return ($statusCode < 100 || $statusCode > 505) ? 500 : $statusCode;
    }

    /**
     * @return string
     */
    protected function wrapperOutput()
    {
        $self = $this;
        $title = $this->getTitle();

        return (function () use ($self, $title) {

            $content = $self->handler->isDisplay()
                ? $self->getStyleSheet()->getHtml()
                : '';

            $stylesheet = $self->getStyleSheet()->getStyleSheet();

            return <<<EOF
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <title>{$title}</title>
        <meta name="robots" content="noindex,nofollow" />
        <style>{$stylesheet}</style>
    </head>
    <body>
    <div id="content" class="reset">
    <h1>{$title}</h1>
    {$content}
    </div>
    </body>
</html>
EOF;
        })();
    }

    /**
     * @return int
     */
    public function send()
    {
        if ($this->isCli()) {
            echo $this->getStyleSheet()->getCli();
            return 0;
        }

        if (!headers_sent()) {
            header(sprintf('HTTP/1.1 %s', $this->filterStatusCode()));
            foreach ($this->getHeaders() as $name => $value) {
                header($name . ': ' . $value, false);
            }
        }

        echo $this->wrapperOutput();

        if ($this->handler->isDisplay()) {
            $render = $this->handler->getBar()->getJavascriptRenderer();

            echo '<pre>';
            print_r($render->getAssets());
        }

        return 0;
    }

    /**
     * 输出错误
     *
     * @param Debug $debug
     * @param Throwable $throwable
     */
    public static function output(Debug $debug, Throwable $throwable)
    {
        $wrapper = new static($debug, $throwable);

        $wrapper->send();
    }
}