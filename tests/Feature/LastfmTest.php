<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LastfmService;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Mockery as m;
use Tymon\JWTAuth\JWTAuth;

class LastfmTest extends TestCase
{
    use WithoutMiddleware;

    public function testGetSessionKey()
    {
        /** @var Client $client */
        $client = m::mock(Client::class, [
            'get' => new Response(200, [], file_get_contents(__DIR__.'../../blobs/lastfm/session-key.xml')),
        ]);

        $this->assertEquals('foo', (new LastfmService($client))->getSessionKey('bar'));
    }

    public function testSetSessionKey()
    {
        $user = factory(User::class)->create();
        $this->postAsUser('api/lastfm/session-key', ['key' => 'foo'], $user);
        $user = User::find($user->id);
        $this->assertEquals('foo', $user->lastfm_session_key);
    }

    public function testConnectToLastfm()
    {
        $this->mockIocDependency(JWTAuth::class, [
            'parseToken' => null,
            'getToken' => 'foo',
        ]);

        $this->getAsUser('api/lastfm/connect')
            ->assertRedirectedTo('https://www.last.fm/api/auth/?api_key=foo&cb=http%3A%2F%2Flocalhost%2Fapi%2Flastfm%2Fcallback%3Fjwt-token%3Dfoo');
    }

    public function testRetrieveAndStoreSessionKey()
    {
        $lastfm = $this->mockIocDependency(LastfmService::class);
        $lastfm->shouldReceive('getSessionKey')
            ->once()
            ->with('foo')
            ->andReturn('bar');

        /** @var User $user */
        $user = factory(User::class)->create();
        $this->getAsUser('api/lastfm/callback?token=foo', $user);
        $user->refresh();

        $this->assertEquals('bar', $user->lastfm_session_key);
    }

    public function testDisconnectUser()
    {
        /** @var User $user */
        $user = factory(User::class)->create(['preferences' => ['lastfm_session_key' => 'bar']]);
        $this->deleteAsUser('api/lastfm/disconnect', [], $user);
        $user->refresh();

        $this->assertNull($user->lastfm_session_key);
    }
}
