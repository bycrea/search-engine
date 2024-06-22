<?php

namespace App\Controller;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class IndexController extends AbstractController
{
    private string $country = "fr";
    public function __construct(private HttpClientInterface $client) {}

    /**
     * @throws TransportExceptionInterface
     */
    #[Route('/api/login', name: 'api_login')]
    public function login(Request $request): Response
    {
        try {
            $clientId = $this->getParameter('api_client_id');
            $clientSecret = $this->getParameter('api_client_secret');

            $this->client->withOptions([]);
            $response = $this->client->request("POST", "https://api.jobijoba.com/v3/$this->country/login", [
                'json' => ['client_id' => $clientId, 'client_secret' => $clientSecret]
            ]);

            $payload = $response->toArray();
            if($payload['code'] === 200 && !empty($payload['token'])) {
                $request->getSession()->set('api_token', $payload['token']);
                return $this->redirectToRoute('app_index');
            }

            throw new Exception("merci de vérifier vos identifiants");
        } catch (Exception $e) {
            return $this->render('index/error.html.twig', [
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * @throws TransportExceptionInterface
     */
    #[Route('/', name: 'app_index')]
    public function index(Request $request): Response
    {
        try {
            $token = $request->getSession()->get('api_token');

            // TODO verify query params to prevent injection
            $query = !empty($request->query->count()) ? $request->query->all() : ['what' => "", 'where' => "", 'limit' => 10, 'page' => 1];

            $response = $this->client->request("GET", "https://api.jobijoba.com/v3/$this->country/ads/search", [
                'headers' => ["Authorization: Bearer $token"],
                'query' => $query
            ]);

            $results = $response->toArray();
        } catch (Exception $e) {
            /* TODO IF EXPIRED TOKEN > LOGIN
            $request->getSession()->set("api_token', null);
            return $this->redirectToRoute('api_login'); */

            return $this->render('index/error.html.twig', [
                'message' => $e->getMessage()
            ]);
        }

        return $this->render('index/index.html.twig', [
            'data' => $results['data'],
            'query' => $query,
            'pagination' => [
                'from' => max($query['page'] - 5, 1),
                'to'   => min(ceil($results['data']['total']/$query['limit']), $query['page'] + 5)
            ]
        ]);
    }
}
