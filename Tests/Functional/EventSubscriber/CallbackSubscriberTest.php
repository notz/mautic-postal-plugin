<?php

declare(strict_types=1);

namespace MauticPlugin\PostalBundle\Tests\Functional\EventSubscriber;

use Mautic\CoreBundle\Helper\DateTimeHelper;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\DoNotContact;
use Mautic\LeadBundle\Entity\Lead;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;

class CallbackSubscriberTest extends MauticMysqlTestCase
{
    protected function setUp(): void
    {

        if ('testPostalTransportNotConfigured' !== $this->name()) {
            $this->configParams['mailer_dsn'] = 'mautic+smtp://:user@host:25';
        }

        parent::setUp();
    }

    public function testPostalTransportNotConfigured(): void
    {
        $this->client->request(Request::METHOD_POST, '/mailer/callback');
        $response = $this->client->getResponse();
        Assert::assertSame('No email transport that could process this callback was found', $response->getContent());
        Assert::assertSame(404, $response->getStatusCode());
    }

    public function testPostalCallbackProcessWithMessageFailed(): void
    {
        $parameters = $this->getFailedParameters();

        $contact = $this->createContact('test@example.com');
        $this->em->flush();

        $now          = new \DateTime();
        $nowFormatted = $now->format(DateTimeHelper::FORMAT_DB);

        $this->client()->request(Request::METHOD_POST, '/mailer/callback', $parameters);
        $response = $this->client()->getResponse();
        Assert::assertSame('Callback processed', $response->getContent());
        Assert::assertSame(200, $response->getStatusCode());

        $dnc = $contact->getDoNotContact()->current();
        Assert::assertSame('email', $dnc->getChannel());
        Assert::assertSame('Hard bounce', $dnc->getComments());
        Assert::assertSame($nowFormatted, $dnc->getDateAdded()->format(DateTimeHelper::FORMAT_DB));
        Assert::assertSame($contact, $dnc->getLead());
        Assert::assertSame(DoNotContact::BOUNCED, $dnc->getReason());
    }

    public function testPostalCallbackProcessWithMessageBounced(): void
    {
        $parameters = $this->getBouncedParameters();

        $contact = $this->createContact('test@example.com');
        $this->em->flush();

        $now          = new \DateTime();
        $nowFormatted = $now->format(DateTimeHelper::FORMAT_DB);

        $this->client->request(Request::METHOD_POST, '/mailer/callback', $parameters);
        $response = $this->client->getResponse();
        Assert::assertSame('Callback processed', $response->getContent());
        Assert::assertSame(200, $response->getStatusCode());

        $dnc = $contact->getDoNotContact()->current();
        Assert::assertSame('email', $dnc->getChannel());
        Assert::assertSame('Delivery failed', $dnc->getComments());
        Assert::assertSame($nowFormatted, $dnc->getDateAdded()->format(DateTimeHelper::FORMAT_DB));
        Assert::assertSame($contact, $dnc->getLead());
        Assert::assertSame(DoNotContact::BOUNCED, $dnc->getReason());
    }

    /**
     * @return array<mixed>
     */
    private function getFailedParameters(): array
    {
        return [
            [
                'event'   => 'MessageFailed',
                'payload' => [
                    'message' => [
                        'id'          => '12345',
                        'token'       => 'abcdef123',
                        'direction'   => 'outgoing',
                        'message_id'  => '5817a64332f44_4ec93ff59e79d154565eb@app34.mail',
                        'to'          => 'test@example.com',
                        'from'        => 'sales@awesomeapp.com',
                        'subject'     => 'Welcome to AwesomeApp',
                        'timestamp'   => 1477945177.12994,
                        'spam_status' => 'NotSpam',
                        'tag'         => 'welcome',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    private function getBouncedParameters(): array
    {
        return [
            [
                'event'   => 'MessageBounced',
                'payload' => [
                    'original_message' => [
                        'id'          => '12345',
                        'token'       => 'abcdef123',
                        'direction'   => 'outgoing',
                        'message_id'  => '5817a64332f44_4ec93ff59e79d154565eb@app34.mail',
                        'to'          => 'test@example.com',
                        'from'        => 'sales@awesomeapp.com',
                        'subject'     => 'Welcome to AwesomeApp',
                        'timestamp'   => 1477945177.12994,
                        'spam_status' => 'NotSpam',
                        'tag'         => 'welcome',
                    ],
                    'bounce' => [
                        'id'          => '12345',
                        'token'       => 'abcdef124',
                        'direction'   => 'incoming',
                        'to'          => 'abcde@psrp.postal.yourdomain.com',
                        'from'        => 'postmaster@someserver.com',
                        'subject'     => 'Delivery Error',
                        'timestamp'   => 1477945179.12994,
                        'spam_status' => 'NotSpam',
                        'tag'         => null,
                    ],
                ],
            ],
        ];
    }

    private function createContact(string $email): Lead
    {
        $lead = new Lead();
        $lead->setEmail($email);

        $this->em->persist($lead);

        return $lead;
    }
}
