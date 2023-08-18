<?php

declare(strict_types=1);

namespace App\Controller\Magazine;

use App\Controller\AbstractController;
use App\Entity\Magazine;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MagazineStyleController extends AbstractController
{
    public function __invoke(Magazine $magazine, Request $request): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/css');
        $response->setPublic();
        $response->setMaxAge(3600);

        if ($magazine->customCss) {
            $response->setContent($magazine->customCss);
        } else {
            $response->setStatusCode(Response::HTTP_NOT_FOUND);
            $response->headers->set('Status', '404 Not Found');
        }

        return $response;
    }
}
