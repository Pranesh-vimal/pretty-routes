<?php

namespace Pranesh\PrettyRoutes\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Console\Input\InputOption;

class PrettyRoutesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'route:pretty';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display all registered routes';

    /**
     * The router instance.
     *
     * @var \Illuminate\Routing\Router
     */
    protected $router;

    /**
     * The terminal instance.
     *
     * @var \Symfony\Component\Console\Terminal
     */
    protected $terminal;

    /**
     * The terminal width.
     *
     * @var int|null
     */
    protected ?int $terminalWidth = null;

    /**
     * The table headers for the command.
     *
     * @var array
     */
    protected $headers = ['Host', 'Method', 'URI', 'Name'];

    /**
     * Create a new route command instance.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @param  \Symfony\Component\Console\Terminal  $terminal
     * @return void
     */
    public function __construct(Router $router, Terminal $terminal)
    {
        parent::__construct();

        $this->router = $router;
        $this->terminal = $terminal;
    }

    /**
     * Computes the terminal width.
     *
     * @return int
     */
    protected function getTerminalWidth()
    {
        if ($this->terminalWidth == null) {
            $this->terminalWidth = $this->terminal->getWidth();

            $this->terminalWidth = $this->terminalWidth >= 30
                ? $this->terminalWidth
                : 30;
        }

        return $this->terminalWidth;
    }

    /**
     * Execute the console command.
     *
     */
    public function handle(): void
    {
        if (method_exists($this->router, 'flushMiddlewareGroups')) {

            $this->router->flushMiddlewareGroups();
        }

        if (empty($this->router->getRoutes())) {

            $this->error("Your application doesn't have any routes.");
            return;
        }

        if (empty($routes = $this->getRoutes())) {

            $this->error("Your application doesn't have any routes matching the given criteria.");
            return;
        }

        if (!$this->option('group')) {

            $this->displayUngroupedRoutes($routes);
        } else {

            $this->displayGroupedRoutes($routes);
        }
    }

    /**
     * Compile the routes into a displayable format.
     *
     * @return array
     */
    protected function getRoutes()
    {
        $routes = collect($this->router->getRoutes())->map(function ($route) {
            return $this->getRouteInformation($route);
        })->filter()->all();

        if ($sort = $this->option('sort')) {
            $routes = $this->sortRoutes($sort, $routes);
        }

        if ($this->option('reverse')) {
            $routes = array_reverse($routes);
        }

        return $routes;
    }

    /**
     * Get the route information for a given route.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return array
     */
    protected function getRouteInformation(Route $route)
    {
        return $this->filterRoute([
            'host' => $route->domain(),
            'method' => implode('|', $route->methods()),
            'uri' => $route->uri(),
            'name' => $route->getName(),
        ]);
    }

    /**
     * Sort the routes by a given element.
     *
     * @param  string  $sort
     * @param  array  $routes
     * @return array
     */
    protected function sortRoutes($sort, array $routes)
    {
        return Arr::sort($routes, function ($route) use ($sort) {
            return $route[$sort];
        });
    }

    /**
     * Filter the route by URI and / or name.
     *
     * @param  array  $route
     * @return array|null
     */
    protected function filterRoute(array $route)
    {
        if ($this->option('method') && !Str::contains($route['method'], strtoupper($this->option('method')))) {
            return null;
        }

        $tempRoute = $route;

        if ($this->option('except-path')) {
            foreach (explode(',', $this->option('except-path')) as $path) {
                if (Str::contains($route['uri'], $path)) {
                    $tempRoute = null;
                }
            }
        }

        if ($this->option('path')) {
            if (count(array_filter(explode(',', $this->option('only-path')), function (string $element) use ($route) {
                return Str::contains($route['uri'], $element);
            })) == 0) {
                $tempRoute = null;
            }
        }

        if ($this->option('except-name')) {
            foreach (explode(',', $this->option('except-name')) as $path) {
                if (Str::contains($route['name'], $path)) {
                    $tempRoute = null;
                }
            }
        }

        if ($this->option('name')) {
            if (count(array_filter(explode(',', $this->option('only-name')), function (string $element) use ($route) {
                return Str::contains($route['name'], $element);
            })) == 0) {
                $tempRoute = null;
            }
        }

        return $tempRoute;
    }

    /**
     * @param  array  $routes
     */
    protected function displayGroupedRoutes(array $routes): void
    {
        $terminalWidth = $this->getTerminalWidth();

        $maxMethod = strlen(collect($routes)->max('method'));

        $groups = [];
        switch (strtolower($this->option('group'))) {
            case 'path':
                $groups = $this->groupRoutesByPath($routes);

                break;
            case 'name':
                $groups = $this->groupRoutesByName($routes);

                break;
            default:
                $this->error("Grouping mode must be 'path' or 'name'.");
        }

        $firstIteration = true;
        foreach ($groups as $group => $routes) {
            if (!$firstIteration) {
                $this->line('');
            }
            $leftSpaces = ($terminalWidth - 14 - strlen($group)) / 2;


            $this->line(sprintf("%s<fg=white;options=bold,underscore>%s</>", str_repeat(' ', $leftSpaces), $group));
            foreach ($routes as $route) {
                $this->output->writeln($this->renderRoute($route, $terminalWidth, $maxMethod));
            }

            $firstIteration = false;
        }
    }

    /**
     * @param  array  $routes
     * @return array
     */
    protected function groupRoutesByPath(array $routes): array
    {
        $groups = [];
        foreach ($routes as $route) {
            $uri = $route["uri"];

            $groupName = explode('/', $uri)[0];
            $groups[$groupName][] = $route;
        }

        if ($this->option('reverse-group')) {
            krsort($groups);
        } else {
            ksort($groups);
        }

        return $groups;
    }

    /**
     * @param  array  $routes
     * @return array
     */
    protected function groupRoutesByName(array $routes): array
    {
        $groups = [];
        foreach ($routes as $route) {
            $name = $route["name"];

            $groupName = explode('.', $name)[0];
            $groups[$groupName][] = $route;
        }

        ksort($groups);

        return $groups;
    }

    /**
     * @param  array  $routes
     */
    protected function displayUngroupedRoutes(array $routes)
    {
        $terminalWidth = $this->getTerminalWidth();

        $maxMethod = strlen(collect($routes)->max('method'));

        foreach ($routes as $route) {
            $this->output->writeln($this->renderRoute($route, $terminalWidth, $maxMethod));
        }
    }

    /**
     * @param  array  $route
     * @param  int  $terminalWidth
     * @param  int  $maxMethod
     * @return string
     */
    protected function renderRoute(array $route, int $terminalWidth, int $maxMethod): string
    {
        $host = $route['host'];
        $method = $route["method"];
        $uri = $route["uri"];
        $name = $route["name"];

        $spaces = str_repeat(' ', max($maxMethod + 6 - strlen($method), 0));

        $additionalSpace = !is_null($name) ? 1 : 0;
        $dots = str_repeat('.', max($terminalWidth - strlen($host . $method . $uri . $name) - strlen($spaces) - 14 - $additionalSpace, 0));

        $method = implode('|', array_map(function ($m) {
            $color = [
                'GET' => 'green',
                'HEAD' => 'default',
                'OPTIONS' => 'default',
                'POST' => 'magenta',
                'PUT' => 'yellow',
                'PATCH' => 'yellow',
                'DELETE' => 'red',
            ][$m] ?? 'white';

            return sprintf("<fg=%s>%s</>", $color, $m);
        }, explode('|', $method)));

        return sprintf(
            '  <fg=white;options=bold>%s</>%s<fg=white;options=bold>%s</><fg=#6C7280> %s </>%s',
            $host,
            $method,
            $spaces,
            preg_replace('#({[^}]+})#', '<comment>$1</comment>', $uri),
            $dots,
            $name,
        );
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['sort', null, InputOption::VALUE_OPTIONAL, 'The column (precedence, domain, method, uri, name) to sort by', 'uri'],
            ['except-path', null, InputOption::VALUE_OPTIONAL, 'Do not display the routes matching the given path pattern (comma-separated values)'],
            ['except-name', null, InputOption::VALUE_OPTIONAL, 'Do not display the routes matching the given name pattern'],
            ['path', null, InputOption::VALUE_OPTIONAL, 'Only show routes matching the given path pattern'],
            ['name', null, InputOption::VALUE_OPTIONAL, 'Filter the routes by name'],
            ['method', null, InputOption::VALUE_OPTIONAL, 'Filter the routes by method'],
            ['group', null, InputOption::VALUE_OPTIONAL, 'Filter the routes by group'],
            ['reverse', 'r', InputOption::VALUE_NONE, 'Reverse the ordering of the routes'],
            ['reverse-group', 'rg', InputOption::VALUE_NONE, 'Reverse the ordering of the route groups'],
        ];
    }
}
