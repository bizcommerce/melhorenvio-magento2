<?php

namespace MelhorEnvio\Quote\Model\Services;

use Laminas\Http\Client as HttpClient;
use Laminas\Http\Exception\ExceptionInterface as HttpExceptionInterface;
use Laminas\Http\Request as HttpRequest;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\LaminasClientFactory as ClientFactory;
use MelhorEnvio\Quote\Api\Data\HttpResponseInterface;
use MelhorEnvio\Quote\Api\HttpClientInterface;
use MelhorEnvio\Quote\Api\LoggerInterface;
use MelhorEnvio\Quote\Api\ServiceInterface;
use MelhorEnvio\Quote\Model\Data\Http\ResponseFactory;

/**
 * Class HttpClient
 * @package MelhorEnvio\Quote\Model\Services\Client
 */
class Client implements HttpClientInterface
{
    /**
     * @var ClientFactory
     */
    private $clientFactory;
    /**
     * @var ResponseFactory
     */
    private $httpResponseFactory;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var \Magento\Framework\App\ProductMetadataInterface
     */
    private $productMetadata;
    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * ZendClient constructor.
     * @param \Magento\Framework\App\ProductMetadataInterface $productMetadata
     * @param ClientFactory $clientFactory
     * @param ResponseFactory $httpResponseFactory
     * @param LoggerInterface $logger
     * @param \Magento\Framework\HTTP\Client\Curl $curl
     */
    public function __construct(
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        ClientFactory $clientFactory,
        ResponseFactory $httpResponseFactory,
        LoggerInterface $logger,
        HttpClient $httpClient
    ) {
        $this->productMetadata = $productMetadata;
        $this->clientFactory = $clientFactory;
        $this->httpResponseFactory = $httpResponseFactory;
        $this->logger = $logger;
        $this->httpClient = $httpClient;
    }

    /**
     * @param ServiceInterface $service
     * @return HttpResponseInterface
     * @throws LocalizedException
     */
    public function doRequest(ServiceInterface $service): HttpResponseInterface
    {
        if ($service->getMethod() == HttpRequest::METHOD_DELETE) {
            return $this->handleMethodDelete($service);
        }

        $headers = $service->getHeaders();
        $headers['User-Agent'] = 'Magento 2/'.$this->productMetadata->getVersion();

        try {
            $this->httpClient->setUri($this->getEndpoint($service));
            $this->httpClient->setMethod($service->getMethod());
            $this->httpClient->setHeaders($service->getHeaders());

            if (!empty($service->getData())
                && $service->getMethod() != HttpRequest::METHOD_GET
            ) {
                $this->httpClient->setRawBody($this->prepareData($service->getData()));
            }

            $this->logger->info($this->getEndpoint($service), [$service->getData()]);
            $this->logger->info('headers', $headers);

            $result = $this->httpClient->send();
        } catch (HttpExceptionInterface $e) {
            $this->logger->error($e->getMessage());
            throw new LocalizedException(__('Resource temporarily unavailable'));
        }

        $this->logger->info($result->getBody());

        return $this->httpResponseFactory->create(['data' => [
            'code' => $result->getStatusCode(),
            'body' => $result->getBody(),
            'headers' => $result->getHeaders()
        ]]);
    }

    /**
     * @param ServiceInterface $service
     * @return HttpResponseInterface
     */
    private function handleMethodDelete(ServiceInterface $service): HttpResponseInterface
    {
        $header = $service->getHeaders();

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->getEndpoint($service),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => HttpRequest::METHOD_DELETE,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_HTTPHEADER => [
                'User-Agent: Magento 2/'.$this->productMetadata->getVersion(),
                'accept: application/json',
                'content-type: application/json',
                sprintf('authorization: %s', $header['Authorization'])
            ],
        ));

        curl_exec($curl);
        $info = curl_getinfo($curl);
        curl_close($curl);

        return $this->httpResponseFactory->create(['data' => [
            'code' => $info['http_code']
        ]]);
    }

    /**
     * @param array $data
     * @return string
     */
    private function prepareData(array $data): string
    {
        return json_encode($data);
    }

    /**
     * @param ServiceInterface $service
     * @return string
     */
    private function getEndpoint(ServiceInterface $service): string
    {
        if (in_array($service->getMethod(), [HttpRequest::METHOD_GET, HttpRequest::METHOD_DELETE])
            && !empty($service->getData())
        ) {
            $queryString = $service->getEndpoint() . '?' . http_build_query($service->getData());
            return str_replace('?0=', '/', $queryString);
        }

        return $service->getEndpoint();
    }
}
