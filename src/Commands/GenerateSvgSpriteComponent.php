<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class GenerateSvgSpriteComponent extends Command
{
    private string $PATH_SVG;
    private string $PATH_OPTIMIZED_SVG;
    private string $PATH_COMPONENT_SVG;
    private array $RULES_OPTIMIZED_SVG;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'generate:svg';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate SVG sprite component';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->PATH_SVG = resource_path("assets/svg");
        $this->PATH_OPTIMIZED_SVG = resource_path("assets/svg/optimized");
        $this->PATH_COMPONENT_SVG = resource_path("views/components/svg");
        $this->RULES_OPTIMIZED_SVG = ["width", "height", "fill"];
    }

    public function handle()
    {
        $this->info("SVG Sprite generation component - Start");

        $this->deleteAllSvgComponent();
        $this->optimizedSvg();
        $this->createSvgComponents();

        $this->info("SVG Sprite generation component - Finish");
    }

    private function deleteAllSvgComponent()
    {
        $this->warn("1. Delete all svg component");
        $paths = glob(rtrim($this->PATH_COMPONENT_SVG, "/") . '/*');

        if (is_array($paths)) {
            foreach ($paths as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    private function createSvgComponents()
    {
        $this->warn("3. Create all svg component");

        if (!is_dir($this->PATH_COMPONENT_SVG)) {
            mkdir($this->PATH_COMPONENT_SVG);
        }

        $svgs = $this->getSourceSvgs();
        foreach ($svgs as $svg) {

            $filename = $svg["filename"];
            $content = $svg["content"];

            $dom = new \DOMDocument();
            $dom->loadXML($content);
            $xpath = new \DOMXPath($dom);
            $nodes = $xpath->query("//*[name()='svg']");
            $node = $nodes[0] ?? null;
            if (!$node) {
                continue;
            }
            $viewbox = $node->getAttribute("viewBox") ?? false;

            // Create component
            $filename_component = str_replace(".svg", ".blade.php", $filename);
            $name = str_replace(".svg", "", $filename);
            $data = "<svg {!!\$attributes!!} viewbox='$viewbox'>" . PHP_EOL;

            if ($svg["optimized"]) {
                $data .= "<use href='#optimized/$name'></use>" . PHP_EOL;
            } else {
                $data .= "<use href='#$name'></use>" . PHP_EOL;
            }

            $data .= "</svg>";
            file_put_contents("$this->PATH_COMPONENT_SVG/$filename_component", $data);
        }
    }

    private function optimizedSvg()
    {
        $this->warn("2. Optimized svg");

        foreach ($this->getSourceOptimizedSvgs() as $svg) {
            $filename = "$this->PATH_OPTIMIZED_SVG/$svg";

            $dom = new \DOMDocument();
            $dom->loadXML(file_get_contents($filename));
            $xpath = new \DOMXPath($dom);
            $nodes = $xpath->query("//*[name()='svg']");
            $node = $nodes[0] ?? [];

            foreach ($this->RULES_OPTIMIZED_SVG as $rule) {
                $node->removeAttribute($rule);
            }

            foreach ($node->childNodes as $child) {
                foreach ($this->RULES_OPTIMIZED_SVG as $rule) {
                    $child->removeAttribute($rule);
                }
            }

            file_put_contents($filename, $dom->saveHTML());
        }
    }

    private function getSourceOptimizedSvgs(): Collection
    {
        return collect(scandir($this->PATH_OPTIMIZED_SVG))->filter(function ($item) {
            return str_contains($item, ".svg");
        })->values();
    }

    private function getSourceSvgs(): Collection
    {
        $svgs = collect(scandir($this->PATH_SVG))->filter(function ($item) {
            return str_contains($item, ".svg");
        })->map(function ($item) {
            return [
                "filename" => $item,
                "content" => file_get_contents("$this->PATH_SVG/$item"),
                "optimized" => false
            ];
        });

        return $svgs->merge(collect(scandir($this->PATH_OPTIMIZED_SVG))->filter(function ($item) {
            return str_contains($item, ".svg");
        })->map(function ($item) {
            return [
                "filename" => $item,
                "content" => file_get_contents("$this->PATH_OPTIMIZED_SVG/$item"),
                "optimized" => true
            ];
        }));
    }
}
