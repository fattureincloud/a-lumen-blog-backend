<?php

use App\Comment;
use App\Post;
use App\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @internal
 */
class UserTest extends TestCase
{
    public function testNewUser()
    {
        $email = 'test@example.com';
        $pwd = 'password';

        $this->notSeeInDatabase('users', ['email' => $email]);

        $this->post('auth/register', ['email' => $email, 'password' => $pwd])
            ->seeStatusCode(200);

        $this->seeInDatabase('users', ['email' => $email]);
    }

    public function testDuplicatedUser()
    {
        $email = 'test@example.com';
        $pwd = 'password';

        factory(User::class)->create([
            'email' => $email,
        ]);

        $this->post('auth/register', ['email' => $email, 'password' => $pwd])
            ->seeStatusCode(422);
    }

    public function testUserUpdate()
    {
        $user = factory(User::class)->create();
        $email = 'test@example.com';
        $password = 'password';

        $this->put('users/' . $user->id, ['email' => $email])
            ->seeStatusCode(401);

        // NOTE: in order to make logout() function working we have to pass the JWT token -> can't use standard actingAs function
        $token = JWTAuth::fromUser($user);
        $this->put('users/' . $user->id . '?token=' . $token, ['email' => $email, 'password' => $password])
            ->seeStatusCode(200);
    }

    public function testUserControllerCoverage()
    {
        $this->get('users/' . 1)
            ->seeStatusCode(404);

        $user = factory(User::class)->create();

        $this->get('users/' . $user->id)
            ->seeStatusCode(200)
            ->seeJsonEquals($user->toArray());

        $this->get('users')
            ->seeStatusCode(200)
            ->seeJsonEquals([$user->toArray()]);
    }

    public function testUserCoverage()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user);
        $post = factory(Post::class)->create([
            'user_id' => $user->id,
        ]);
        $comment = factory(Comment::class)->create([
            'post_id' => $post->id,
            'user_id' => $user->id,
        ]);

        $post = Post::find($post->id);
        $comment = Comment::find($comment->id);

        $this->assertEquals([$post->toArray()], $user->posts()->get()->toArray());
        $this->assertEquals([$comment->toArray()], $user->comments()->get()->toArray());
    }

    public function testUserNewValidation()
    {
        $this->post('auth/register')
            ->seeStatusCode(422);

        $this->post('auth/register', ['email' => 'test', 'password' => 'password'])
            ->seeStatusCode(422);

        $this->post('auth/register', ['email' => 'test@email.com'])
            ->seeStatusCode(422);

        $this->post('auth/register', ['email' => 'test@email.com', 'password' => 'password'])
            ->seeStatusCode(200);
    }

    public function testUserEditValidation()
    {
        factory(User::class)->create([
            'email' => 'test@email.com',
        ]);
        $user = factory(User::class)->create();

        $this->actingAs($user);
        $this->put('users/' . $user->id, ['email' => ''])
            ->seeStatusCode(422);

        $this->put('users/' . $user->id, ['email' => 'test'])
            ->seeStatusCode(422);

        $this->put('users/' . $user->id, ['email' => 'test@email.com'])
            ->seeStatusCode(409);

        $this->put('users/' . ($user->id + 1), ['email' => 'foo@bar.com'])
            ->seeStatusCode(404);

        $this->put('users/' . $user->id, ['email' => 'foo@bar.com'])
            ->seeStatusCode(200);
    }
}
