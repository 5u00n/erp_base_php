<?php

namespace App\Controllers;

use App\Models\PaymentModel;
use App\Config;
use Stripe\StripeClient;

class PaymentController
{
    public function list(array $req): void
    {
        $projectId = $req['params']['projectId'];
        $payments  = PaymentModel::listByProject($projectId);

        $this->json([
            'payments' => array_map(fn($p) => [
                'id'                    => $p['id'],
                'stripePaymentIntentId' => $p['stripe_payment_intent_id'],
                'amountCents'           => (int) $p['amount_cents'],
                'currency'              => $p['currency'],
                'status'                => $p['status'],
                'createdAt'             => $p['created_at'],
            ], $payments),
        ]);
    }

    public function createIntent(array $req): void
    {
        $projectId   = $req['params']['projectId'];
        $body        = $req['body'] ?? [];
        $amountCents = (int) ($body['amountCents'] ?? 0);
        $currency    = $body['currency'] ?? 'usd';

        if ($amountCents <= 0) {
            $this->json(['error' => 'invalid_body'], 400);
            return;
        }

        $stripeKey = Config::get()->stripeSecretKey;
        $id = $this->uuid();

        if (!$stripeKey) {
            $p = PaymentModel::create(
                $id, $projectId, $amountCents, $currency, 'stub', null,
                json_encode(['note' => 'Configure STRIPE_SECRET_KEY for live mode'])
            );
            $this->json([
                'payment' => [
                    'id'          => $p['id'],
                    'status'      => $p['status'],
                    'amountCents' => (int) $p['amount_cents'],
                    'currency'    => $p['currency'],
                    'createdAt'   => $p['created_at'],
                ],
            ], 201);
            return;
        }

        $stripe = new StripeClient($stripeKey);
        $intent = $stripe->paymentIntents->create([
            'amount'                    => $amountCents,
            'currency'                  => $currency,
            'automatic_payment_methods' => ['enabled' => true],
            'metadata'                  => ['projectId' => $projectId],
        ]);

        $p = PaymentModel::create(
            $id, $projectId, $amountCents, $currency, $intent->status,
            $intent->id, json_encode(['client_secret' => $intent->client_secret])
        );

        $this->json([
            'payment' => [
                'id'                    => $p['id'],
                'stripePaymentIntentId' => $intent->id,
                'clientSecret'          => $intent->client_secret,
                'status'                => $intent->status,
                'amountCents'           => (int) $p['amount_cents'],
                'currency'              => $p['currency'],
                'createdAt'             => $p['created_at'],
            ],
        ], 201);
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data);
    }

    private function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
        );
    }
}
