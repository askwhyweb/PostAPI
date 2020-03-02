<?php

namespace OrviSoft\Cloudburst\Plugin;

use OrviSoft\Cloudburst\Plugin\Logger\LoggerInterface;
use OrviSoft\Cloudburst\Plugin\DTO\Order;
use OrviSoft\Cloudburst\Plugin\Event\ConfigurationGet;
use OrviSoft\Cloudburst\Plugin\Event\OrderStatusModified;
use OrviSoft\Cloudburst\Plugin\Event\ProductModified;
use OrviSoft\Cloudburst\Plugin\Event\ProductPriceModified;
use OrviSoft\Cloudburst\Plugin\Event\ProductStockModified;
use OrviSoft\Cloudburst\Plugin\Event\ProductSync;

class Bridge
{
    private $callbacks = [];
    private $connector;
    private $queue;
    private $logger;
    private $host_version;
    private $integration_version;

    function __construct(Connector $connector, Queue $queue, LoggerInterface $logger)
    {
        $this->connector = $connector;
        $this->queue = $queue;
        $this->logger = $logger;
        $this->connector->setRetries(0);
    }

    public function setCredentials($client_name, $source, $auth)
    {
        $this->connector->setClientName($client_name);
        $this->connector->setSource($source);
        $this->connector->setAuth($auth);
    }

    public function sendOrder(Order $order)
    {
        $event = Functions::factory_event()
                          ->create('order_created');
        $event->addOrder($order->toArray());
        $event = $this->connector->addAuthDetails($event);
        $meta['version'] = [
            'plugin'      => $this->getPluginVersion(),
            'integration' => $this->getIntegrationVersion(),
            'host'        => $this->getHostVersion(),
        ];
        $event->setMeta($meta, true);
        return $this->queue->send($event, $this->connector);
    }

    public function createOrder()
    {
        return Functions::factory_dto()
                        ->create('order');
    }

    public function registerCallback($event_code, $callback)
    {
        $this->callbacks[$event_code] = $callback;
        return $this;
    }

    public function getCallbacks()
    {
        return $this->callbacks;
    }

    function listen($data = null, array $server = null)
    {
        if ($data === null) {
            $data = \file_get_contents('php://input');
        }
        if ($server === null) {
            $server = $_SERVER;
        }
        $this->logger->log('Receiving data: ' . $data);
        $ret = $this->connector->parseRequest($data, $server);
        if ($ret->status !== 1) {
            $this->logger->log('Sending data: ' . $ret->body);
            foreach ($ret->headers as $header) {
                \header($header);
            }
            print_r($ret->body);
            return;
        }
        $method = 'processEvent' . $ret->code;
        $output = (array) $this->{$method}($ret->event);
        $output['meta']['version']['plugin'] = $this->getPluginVersion();
        $output['meta']['version']['integration'] = $this->getIntegrationVersion();
        $output['meta']['version']['host'] = $this->getHostVersion();
        $output = \json_encode($output);
        $this->logger->log('Sending data: ' . $output);
        \header('Content-Type: application/json');
        print_r($output);
    }

    public function processEventProductSync(ProductSync $event)
    {
        $cart_product_ids = [];
        $errors = [];
        foreach ($event->getProducts() as $product) {
            $cart_product_id = null;
            $product_key = isset($product['sourceId']) ? $product['sourceId'] : $product['sku'];
            \ob_start();
            try {
                $ret = \call_user_func($this->callbacks['product_sync'], $product);
                if (\is_array($ret)) {
                    $errors[$product_key] = $ret;
                } else {
                    $cart_product_id = $ret;
                }
                $cart_product_ids[$product_key] = $cart_product_id;
                if ($cart_product_id !== null) {
                    $this->logger->log('Setting product: ' . $product_key . '. Internal id: ' . $cart_product_id);
                }
            } catch (\Exception $e) {
                $this->logger->log($e);
                $errors[$product_key] = ['general' => 'Exception: ' . $e->getMessage()];
            }
            $unexpected_output = \ob_get_clean();
            if ($unexpected_output !== '') {
                $errors[$product_key]['unexpected_output'] = $unexpected_output;
            }
        }
        return [
            'response' => $cart_product_ids,
            'errors'   => $errors,
        ];
    }

    public function processEventProductStockModified(ProductStockModified $event)
    {
        $errors = [];
        foreach ($event->getProducts() as $product) {
            $product_key = isset($product['sku']) ? $product['sku'] : $product['id'];
            \ob_start();
            try {
                $this->logger->log('Updating stock for product: ' .
                                   $product_key .
                                   '. New stock: ' .
                                   $product['stock']
                );
                $callback_errors = \call_user_func($this->callbacks['product_stock_modified'], $product);
                if ($callback_errors) {
                    $errors[$product_key] = $callback_errors;
                    $this->logger->log('Errors: ' . print_r($callback_errors, true));
                }
            } catch (\Exception $e) {
                $this->logger->log($e);
                $errors[$product_key] = ['general' => 'Exception: ' . $e->getMessage()];
            }
            $unexpected_output = \ob_get_clean();
            if ($unexpected_output !== '') {
                $errors[$product_key]['unexpected_output'] = $unexpected_output;
            }
        }
        return [
            'response' => true,
            'errors'   => $errors,
        ];
    }

    public function processEventProductPriceModified(ProductPriceModified $event)
    {
        $errors = [];
        foreach ($event->getProducts() as $product) {
            $product_key = isset($product['sku']) ? $product['sku'] : $product['id'];
            \ob_start();
            $this->standardisePrices($product);
            try {
                $this->logger->log('Updating prices for product: ' .
                                   $product_key .
                                   '. New prices: ' .
                                   \json_encode($product['prices'])
                );
                $callback_errors = \call_user_func($this->callbacks['product_price_modified'], $product);
                if ($callback_errors) {
                    $errors[$product_key] = $callback_errors;
                    $this->logger->log('Errors: ' . print_r($callback_errors, true));
                }
            } catch (\Exception $e) {
                $this->logger->log($e);
                $errors[$product_key] = ['general' => 'Exception: ' . $e->getMessage()];
            }
            $unexpected_output = \ob_get_clean();
            if ($unexpected_output !== '') {
                $errors[$product_key]['unexpected_output'] = $unexpected_output;
            }
        }
        return [
            'response' => true,
            'errors'   => $errors,
        ];
    }

    public function processEventProductModified(ProductModified $event)
    {
        $cart_product_ids = [];
        $errors = [];
        foreach ($event->getProducts() as $product) {
            $cart_product_id = null;
            $product_key = isset($product['sourceId']) ? $product['sourceId'] : $product['identity']['sku'];
            \ob_start();
            $this->standardisePrices($product);
            try {
                $ret = \call_user_func($this->callbacks['product_modified'], $product);
                if (\is_array($ret)) {
                    $errors[$product_key] = $ret;
                } else {
                    $cart_product_id = $ret;
                }
                $cart_product_ids[$product_key] = $cart_product_id;
                if ($cart_product_id !== null) {
                    $this->logger->log('Setting product: ' . $product_key . '. Internal id: ' . $cart_product_id);
                }
            } catch (\Exception $e) {
                $this->logger->log($e);
                $errors[$product_key] = ['general' => 'Exception: ' . $e->getMessage()];
            }
            $unexpected_output = \ob_get_clean();
            if ($unexpected_output !== '') {
                $errors[$product_key]['unexpected_output'] = $unexpected_output;
            }
        }
        return [
            'response' => $cart_product_ids,
            'errors'   => $errors,
        ];
    }

    private function standardisePrices(&$product)
    {
        if (isset($product['prices'])) {
            foreach ($product['prices'] as $key => $price) {
                if (!\is_array($price)) {
                    $product['prices'][$key] = ["1" => $price];
                }
            }
        }
    }

    public function processEventOrderStatusModified(OrderStatusModified $event)
    {
        $errors = [];
        foreach ($event->getOrders() as $order) {
            $order_key = $order['id'];
            \ob_start();
            try {
                $this->logger->log('Updating status for order: ' .
                                   $order_key .
                                   '. New status: ' .
                                   $order['statusId']
                );
                $callback_errors = \call_user_func($this->callbacks['order_status_modified'], $order);
                if ($callback_errors) {
                    $errors[$order_key] = $callback_errors;
                }
            } catch (\Exception $e) {
                $this->logger->log($e);
                $errors[$order_key] = ['general' => 'Exception: ' . $e->getMessage()];
            }
            $unexpected_output = \ob_get_clean();
            if ($unexpected_output !== '') {
                $errors[$order_key]['unexpected_output'] = $unexpected_output;
            }
        }
        return [
            'response' => true,
            'errors'   => $errors,
        ];
    }

    public function processEventConfigurationGet(ConfigurationGet $event)
    {
        $response = [];
        $errors = [];
        try {
            $this->logger->log('Reading configuration');
            $response = \call_user_func($this->callbacks['configuration_get']);
        } catch (\Exception $e) {
            $this->logger->log($e);
            $errors = ['general' => 'Exception: ' . $e->getMessage()];
        }
        $unexpected_output = \ob_get_clean();
        if ($unexpected_output !== '') {
            $errors['unexpected_output'] = $unexpected_output;
        }
        return [
            'response' => $response,
            'errors'   => $errors,
        ];
    }

    public function setHostVersion($host_version)
    {
        $this->host_version = $host_version;
    }

    public function getHostVersion()
    {
        return $this->host_version;
    }

    public function setIntegrationVersion($integration_version)
    {
        $this->integration_version = $integration_version;
    }

    public function getIntegrationVersion()
    {
        return $this->integration_version;
    }

    public function getPluginVersion()
    {
        return MOTHERCLOUD_BRIDGE_VERSION;
    }
}
