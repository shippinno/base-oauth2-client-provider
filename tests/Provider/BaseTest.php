<?php

namespace Shippinno\Base\OAuth2\Client\Tests\Provider;

use GuzzleHttp\ClientInterface;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Shippinno\Base\OAuth2\Client\Provider\BaseProvider;

class BaseTest extends TestCase
{
    /**
     * @var BaseProvider
     */
    protected $provider;

    protected function setUp(): void
    {
        $this->provider = new BaseProvider([
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri' => 'mock_redirect_uri',
        ]);
    }

    public function testAuthorizationUrl()
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        parse_str($uri['query'], $query);

        $this->assertArrayHasKey('client_id', $query);
        $this->assertArrayHasKey('redirect_uri', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('scope', $query);
        $this->assertArrayHasKey('response_type', $query);
        $this->assertArrayHasKey('approval_prompt', $query);
        $this->assertNotNull($this->provider->getState());
    }

    public function testResourceOwnerDetailsUrl()
    {
        $token = Mockery::mock(AccessToken::class);
        $url = $this->provider->getResourceOwnerDetailsUrl($token);
        $uri = parse_url($url);
        $this->assertEquals('/1/users/me', $uri['path']);
    }

    public function testGetAccessToken()
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream
            ->method('__toString')
            ->willReturn('{"access_token":"mock_access_token","scope":"email","token_type":"bearer"}');

        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getBody')->andReturn($stream);
        $response->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $client = Mockery::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->once()->andReturn($response);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $this->assertEquals('mock_access_token', $token->getToken());
        $this->assertNull($token->getExpires());
        $this->assertNull($token->getRefreshToken());
        $this->assertNull($token->getResourceOwnerId());
    }

    /**
     * @expectException League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    public function testExceptionThrownWhenErrorObjectReceived()
    {
        $status = rand(400, 600);
        $stream = $this->createMock(StreamInterface::class);
        $stream
            ->method('__toString')
            ->willReturn('{"error_description":"ERROR_DESCRIPTION","error":"some_error"}');

        $postResponse = Mockery::mock(ResponseInterface::class);
        $postResponse->shouldReceive('getBody')->andReturn($stream);
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'application/json;charset=UTF-8']);
        $postResponse->shouldReceive('getStatusCode')->andReturn($status);
        $client = Mockery::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->once()->andReturn($postResponse);
        $this->provider->setHttpClient($client);

        $this->expectException(IdentityProviderException::class);
        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }

    public function testUserData()
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream
            ->method('__toString')
            ->willReturn('{"access_token":"mock_access_token","scope":"email","token_type":"bearer"}');

        $shopId = null;
        $shopName = null;
        $postResponse = Mockery::mock(ResponseInterface::class);
        $postResponse->shouldReceive('getBody')->andReturn($stream);
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'application/json;charset=UTF-8']);
        $accountResponse = Mockery::mock(ResponseInterface::class);
        $accountResponse->shouldReceive('getBody')->andReturn('{"user":{"shop_id":"'.$shopId.'","shop_name":"'.$shopName.'","shop_introduction":null,"shop_url":"'.$shopId.'","twitter_id":null,"facebook_id":null,"ameba_id":null,"instagram_id":null,"background":null,"display_background":0,"repeat_background":1,"logo":null,"display_logo":0}}');
        $accountResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'application/json;charset=UTF-8']);
        $accountResponse->shouldReceive('getStatusCode')->andReturn(200);
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('send')
            ->times(2)
            ->andReturn($postResponse, $accountResponse);
        $this->provider->setHttpClient($client);
        $token = new AccessToken(['access_token' => 'mock_access_token', 'expires_in' => 3600]);
        $account = $this->provider->getResourceOwner($token);

        $this->assertEquals($shopId, $account->getId());
    }
}
