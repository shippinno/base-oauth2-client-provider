<?php

namespace Shippinno\Base\OAuth2\Client\Tests\Provider;

use GuzzleHttp\ClientInterface;
use League\OAuth2\Client\Token\AccessToken;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Shippinno\Base\OAuth2\Client\Provider\BaseProvider;

class BaseTest extends TestCase
{
    /**
     * @var BaseProvider
     */
    protected $provider;

    protected function setUp()
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
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getBody')->andReturn('{"access_token":"mock_access_token", "token_type":"bearer"}');
        $response->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('send')->once()->andReturn($response);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $this->assertEquals('mock_access_token', $token->getToken());
        $this->assertNull($token->getExpires());
        $this->assertNull($token->getRefreshToken());
        $this->assertNull($token->getResourceOwnerId());
    }

    /**
     * @expectedException League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    public function testExceptionThrownWhenErrorObjectReceived()
    {
        $message = uniqid();
        $status = rand(400, 600);
        $postResponse = Mockery::mock(ResponseInterface::class);
        $postResponse->shouldReceive('getBody')->andReturn(' {"error":"' . $message . '"}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn($status);
        $client = Mockery::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->once()->andReturn($postResponse);
        $this->provider->setHttpClient($client);
        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }

    public function testUserData()
    {
        $shopId = uniqid();
        $shopName = uniqid();
        $postResponse = Mockery::mock(ResponseInterface::class);
        $postResponse->shouldReceive('getBody')->andReturn('access_token=mock_access_token&expires=3600&refresh_token=mock_refresh_token');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'application/x-www-form-urlencoded']);
        $postResponse->shouldReceive('getStatusCode')->andReturn(200);
        $accountResponse = Mockery::mock(ResponseInterface::class);
        $accountResponse->shouldReceive('getBody')->andReturn('{"user":{"shop_id":"'.$shopId.'","shop_name":"'.$shopName.'","shop_introduction":null,"shop_url":"'.$shopId.'","twitter_id":null,"facebook_id":null,"ameba_id":null,"instagram_id":null,"background":null,"display_background":0,"repeat_background":1,"logo":null,"display_logo":0}}');
        $accountResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $accountResponse->shouldReceive('getStatusCode')->andReturn(200);
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('send')
            ->times(2)
            ->andReturn($postResponse, $accountResponse);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $account = $this->provider->getResourceOwner($token);

        $this->assertEquals($shopId, $account->getId());
        $this->assertEquals($shopName, $account->getShopName());
        $this->assertEquals($shopId, $account->toArray()['user']['shop_id']);
    }
}
