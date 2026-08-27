<?php
class app_Libs_Router
{
    const PARAM_NAME = "r";
    const HOME_PAGE  = "home";
    const INDEX_PAGE = "index";
    public static $sourcePath;
    public function __construct($sourcePath = "")
    {
        if ($sourcePath)
            self::$sourcePath = $sourcePath;
    }
    // ── GET / POST helpers ──────────────────────────────────────────────
    public function getGET($name = NULL)
    {
        return $name !== NULL ? ($_GET[$name] ?? NULL) : $_GET;
    }
    public function getPOST($name = NULL)
    {
        return $name !== NULL ? ($_POST[$name] ?? NULL) : $_POST;
    }
    // ── Core router ─────────────────────────────────────────────────────
    public function router()
    {
        $url = $this->getGET(self::PARAM_NAME);
        if (!is_string($url) || !$url || $url === self::INDEX_PAGE) {
            $url = self::HOME_PAGE;
        }
        // Chặn path traversal (../ hay %2F...)
        $url  = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $url);
        $path = self::$sourcePath . "/" . $url . ".php";
        if (file_exists($path)) {
            // Truyền $router vào view — header.php & home.php đều cần
            $router = $this;
            return require $path;          // không dùng require_once: cho phép
        }                                  // load lại nếu cần trong test
        return $this->pageNotFound();
    }
    // ── Render partial (header / footer dùng chung) ──────────────────────
    /**
     * Dùng trong view:
     *   $router->partial('header');   // load views/partials/header.php
     *   $router->partial('footer');
     */
    public function partial(string $name): void
    {
        $router = $this;
        // partials nằm trong views/partials/ — sourcePath đã là views/
        $path = self::$sourcePath . "/partials/" . $name . ".php";
        if (file_exists($path)) {
            require $path;
        } else {
            $this->pageError("Partial not found: $name");
        }
    }
    // ── URL helpers ──────────────────────────────────────────────────────
    public function createUrl(string $url, array $params = []): string
    {
        if ($url) $params[self::PARAM_NAME] = $url;
        return $_SERVER['PHP_SELF'] . '?' . http_build_query($params);
    }
    public function redirect(string $url): void
    {
        header("Location: " . $this->createUrl($url));
        exit;
    }
    // ── Login / logout public links ──────────────────────────────────────
    public function publicLogin(): string
    {
        return "../admin/index.php?r=login";
    }
    public function publicLogout(): string
    {
        return "../admin/index.php?r=logout";
    }

    // ── Redirect shortcuts ───────────────────────────────────────────────
    public function adminPage(): void
    {
        header("Location: index.php");
        exit;
    }
    public function userPage(): void
    {
        header("Location: ../public/index.php");
        exit;
    }
    public function loginPage(): void
    {
        header("Location: ../admin/index.php?r=login");
        exit;
    }
public function sellerPage(): void
{
    header("Location: /MantaMarket/sellers/index.php");
    exit;
}
    // ── Error pages ──────────────────────────────────────────────────────
    public function pageNotFound(): void
    {
        http_response_code(404);
        $this->pageError("404 – Page Not Found");
    }
    public function pageError(string $error): void
    {
        echo htmlspecialchars($error);
        exit;
    }
    
}
