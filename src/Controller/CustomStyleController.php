<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Magazine;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CustomStyleController extends AbstractController
{
    public function magazineCustomStyle(Magazine $magazine, Request $request): Response
    {
        $response = $this->createResponse($request, $magazine->customCss);
        $response->setPublic();

        return $response;
    }

    #[IsGranted('ROLE_USER')]
    public function userCustomStyle(Request $request): Response
    {
        $user = $this->getUser();

        $response = $this->createResponse($request, $user->customCss);
        $response->setPrivate();

        return $response;
    }

    private function createResponse(Request $request, ?string $customCss): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/css');
        $response->setMaxAge(60);
        $response->headers->addCacheControlDirective('must-revalidate', true);

        if ($customCss) {
            $response->setContent($customCss);
            $response->setEtag(md5($response->getContent()));
            $response->isNotModified($request);
        } else {
            $response->setStatusCode(Response::HTTP_NOT_FOUND);
            $response->headers->set('Status', '404 Not Found');
        }

        return $response;
    }
}
