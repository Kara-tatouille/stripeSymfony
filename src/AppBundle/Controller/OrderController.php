<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Product;
use AppBundle\Entity\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;

class OrderController extends BaseController
{
    /**
     * @Route("/cart/product/{slug}", name="order_add_product_to_cart")
     * @Method("POST")
     */
    public function addProductToCartAction(Product $product)
    {
        $this->get('shopping_cart')
            ->addProduct($product);

        $this->addFlash('success', 'Product added!');

        return $this->redirectToRoute('order_checkout');
    }

    /**
     * @Route("/checkout", name="order_checkout")
     * @Security("is_granted('ROLE_USER')")
     */
    public function checkoutAction(Request $request)
    {
        $products = $this->get('shopping_cart')->getProducts();

        if ($request->isMethod('POST')) {
            $token = $request->get('stripeToken');

            \Stripe\Stripe::setApiKey($this->getParameter('stripe_secret_key'));

            /** @var User $user */
            $user = $this->getUser();
            if (!$user->getStripeCustomerId()) {
                $customer = \Stripe\Customer::create(
                    [
                        'email' => $user->getEmail(),
                        "source" => $token, // obtained with Stripe.js
                    ]
                );

                $user->setStripeCustomerId($customer->id);
                $em = $this->getDoctrine()->getEntityManager();
                $em->persist($user);
                $em->flush();
            } else {
                $customer = \Stripe\Customer::retrieve($user->getStripeCustomerId());
                $customer->sources = $token;
                $customer->save(); // Save the new source to Stripe.com
            }

            \Stripe\Charge::create(
                [
                    "amount" => $this->get('shopping_cart')->getTotal() * 100,
                    "currency" => "usd",
                    "customer" => $user->getStripeCustomerId(), // If you don't have a customer, replace with "source" => $token
                    "description" => "First test charge",
                ]
            );

            $this->get('shopping_cart')->emptyCart();
            $this->addFlash('success', 'Order complete!');

            return $this->redirectToRoute('homepage');
        }

        return $this->render(
            'order/checkout.html.twig', array(
            'products' => $products,
            'cart' => $this->get('shopping_cart'),
            'stripe_public_key' => $this->getParameter('stripe_public_key'),
        )
        );
    }
}