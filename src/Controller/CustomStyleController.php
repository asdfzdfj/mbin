<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\AbstractController;
use App\Entity\Magazine;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomStyleController extends AbstractController
{
    public function magazineCustomStyle(Magazine $magazine, Request $request): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/css');
        $response->setPublic();
        $response->setMaxAge(60);
        $response->headers->addCacheControlDirective('must-revalidate', true);

        if ($magazine->customCss) {
            $response->setContent($magazine->customCss);
            $response->setEtag(md5($response->getContent()));
            $response->isNotModified($request);
        } else {
            $response->setStatusCode(Response::HTTP_NOT_FOUND);
            $response->headers->set('Status', '404 Not Found');
        }

        return $response;
    }
}
