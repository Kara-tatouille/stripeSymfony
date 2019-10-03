<?php

namespace AppBundle;

use AppBundle\Entity\User;
use Doctrine\ORM\EntityManager;
use Stripe\Exception\ApiErrorException;
use Stripe\Invoice;

class StripeClient
{
    private $em;

    public function __construct($secretKey, EntityManager $em)
    {

        $this->em = $em;
        \Stripe\Stripe::setApiKey($secretKey);
    }

    public function createCustomer(User $user, $paymentToken)
    {
        $customer = \Stripe\Customer::create(
            [
                'email' => $user->getEmail(),
                "source" => $paymentToken, // obtained with Stripe.js
            ]
        );

        $user->setStripeCustomerId($customer->id);
        $em = $this->em;
        $em->persist($user);
        $em->flush();

        return $customer;
    }

    public function updateCustomerCard(User $user, $paymentToken)
    {
        $customer = \Stripe\Customer::retrieve($user->getStripeCustomerId());
        $customer->source = $paymentToken; // IMPORTANT! do not use $customer->sources as it does not work, use $customer->source.
        $customer->save(); // Save the new source to Stripe.com
    }

    public function createInvoiceItem($amount, User $user, $description)
    {
        return \Stripe\InvoiceItem::create(
            [
                "amount" => $amount,
                "currency" => "usd",
                "customer" => $user->getStripeCustomerId(), // If you don't have a customer, replace with "source" => $token
                "description" => $description,
            ]
        );
    }

    /**
     * @param User $user
     * @param bool $payImmediately
     *
     * @return Invoice
     * @throws ApiErrorException
     */
    public function createInvoice(User $user, $payImmediately = true)
    {
        $invoice = Invoice::create(
            [
                'customer' => $user->getStripeCustomerId(),
            ]
        );
        $invoice->pay();

        return $invoice;
    }
}