<?php

declare(strict_types=1);

namespace Bolt\Article\Controller;

use Bolt\Article\ArticleConfig;
use Bolt\Configuration\Config;
use Bolt\Controller\Backend\Async\AsyncZoneInterface;
use Bolt\Controller\CsrfTrait;
use Bolt\Twig\TextExtension;
use Bolt\Utils\ThumbnailHelper;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Tightenco\Collect\Support\Collection;

#[Security("is_granted('list_files:files')")]
class Images implements AsyncZoneInterface
{
    use CsrfTrait;

    private Request $request;

    public function __construct(
        private readonly Config          $config,
        private readonly ThumbnailHelper $thumbnailHelper,
        private readonly ArticleConfig   $articleConfig,
        CsrfTokenManagerInterface        $csrfTokenManager,
        RequestStack                     $requestStack,
    ) {
        $this->csrfTokenManager = $csrfTokenManager;
        $this->request = $requestStack->getCurrentRequest();
    }

    #[Route('/article_images', name: 'bolt_article_images', methods: ['GET'])]
    public function getImagesList(): JsonResponse
    {
        try {
            $this->validateCsrf('bolt_article');
        } catch (InvalidCsrfTokenException $e) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        $locationName = $this->request->query->get('location', 'files');
        // not used, not sure why it's here?
        // $type = $this->request->query->get('type', '');

        $path = $this->config->getPath($locationName);

        $files = $this->getImageFilesIndex($path);

        return new JsonResponse($files);
    }

    private function getImageFilesIndex(string $path): Collection
    {
        $glob = '*.{' . implode(',', self::getImageTypes()) . '}';

        $files = [];

        foreach ($this->findFiles($path, $glob) as $file) {
            $files[] = [
                'thumb' => $this->thumbnailHelper->path($file->getRelativePathname(), 400, 300, null, null, 'crop'),
                'url' => $thumbnail = '/thumbs/' . $this->articleConfig->getConfig()['image']['thumbnail'] . '/' . $file->getRelativePathname(),
            ];
        }

        return new Collection($files);
    }

    #[Route('/article_files', name: 'bolt_article_files', methods: ['GET'])]
    public function getFilesList(): JsonResponse
    {
        try {
            $this->validateCsrf('bolt_article');
        } catch (InvalidCsrfTokenException $e) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        $locationName = $this->request->query->get('location', 'files');
        // not used, not sure why its here?
        //$type = $this->request->query->get('type', '');

        $path = $this->config->getPath($locationName);

        $files = $this->getFilesIndex($path);

        return new JsonResponse($files);
    }

    private function getFilesIndex(string $path): Collection
    {
        $fileTypes = $this->config->getFileTypes()->toArray();
        $glob = '*.{' . implode(',', $fileTypes) . '}';

        $files = [];

        $textExtension = new TextExtension();

        foreach ($this->findFiles($path, $glob) as $file) {
            $files[] = [
                'title' => $file->getRelativePathname(),
                'url' => '/files/' . $file->getRelativePathname(),
                'size' => $textExtension->formatBytes($file->getSize(), 1),
            ];
        }

        return new Collection($files);
    }

    private function findFiles(string $path, ?string $glob = null): Finder
    {
        $finder = new Finder();
        $finder->in($path)->depth('< 3')->sortByType()->files();

        if ($glob) {
            $finder->name($glob);
        }

        return $finder;
    }

    private static function getImageTypes(): array
    {
        return ['gif', 'png', 'jpg', 'jpeg', 'svg', 'avif', 'webp'];
    }
}
