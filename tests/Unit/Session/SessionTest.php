<?php

declare(strict_types=1);

use Slendie\Session\ArraySessionHandler;
use Slendie\Session\FileSessionHandler;
use Slendie\Session\Session;

beforeEach(function (): void {
    $this->handler = new ArraySessionHandler();
    $this->session = new Session('s', $this->handler);
    $this->session->start();
});

function reopen(Session $session): Session
{
    $next = new Session('s', $session->handler(), $session->id());
    $next->start();

    return $next;
}

it('gera um id válido e rejeita ids malformados', function (): void {
    expect($this->session->id())->toMatch('/^[A-Za-z0-9]{40}$/');

    $fixed = new Session('s', $this->handler, 'x/../../etc');

    expect($fixed->id())->not->toBe('x/../../etc')->toMatch('/^[A-Za-z0-9]{40}$/');
});

it('get/put/has/exists/forget/pull/flush/all', function (): void {
    $s = $this->session;
    $s->put('a', 1);
    $s->put(['b' => ['c' => 2]]);
    $s->put('nul', null);

    expect($s->get('a'))->toBe(1)
        ->and($s->get('b.c'))->toBe(2)
        ->and($s->has('a'))->toBeTrue()
        ->and($s->has('nul'))->toBeFalse()
        ->and($s->exists('nul'))->toBeTrue()
        ->and($s->get('zzz', 'd'))->toBe('d')
        ->and($s->pull('a'))->toBe(1)
        ->and($s->has('a'))->toBeFalse();

    $s->forget('b.c');
    expect($s->get('b'))->toBe([]);

    $s->flush();
    expect($s->all())->toBe([]);
});

it('push, increment e decrement', function (): void {
    $s = $this->session;
    $s->push('cart', 'a');
    $s->push('cart', 'b');

    expect($s->get('cart'))->toBe(['a', 'b'])
        ->and($s->increment('hits'))->toBe(1)
        ->and($s->increment('hits', 5))->toBe(6)
        ->and($s->decrement('hits'))->toBe(5);
});

it('persiste entre pedidos através do handler', function (): void {
    $this->session->put('user', 'ana');
    $this->session->save();

    $next = reopen($this->session);

    expect($next->get('user'))->toBe('ana')
        ->and($next->token())->toBe($this->session->token());
});

it('flash dura exactamente um pedido (ageing em save)', function (): void {
    $this->session->flash('status', 'ok');
    expect($this->session->get('status'))->toBe('ok');
    $this->session->save();

    $second = reopen($this->session);
    expect($second->get('status'))->toBe('ok');
    $second->save();

    $third = reopen($second);
    expect($third->get('status'))->toBeNull();
});

it('now() só vale para o pedido actual', function (): void {
    $this->session->now('tmp', 'x');
    expect($this->session->get('tmp'))->toBe('x');
    $this->session->save();

    expect(reopen($this->session)->get('tmp'))->toBeNull();
});

it('reflash() e keep() prolongam o flash por mais um pedido', function (): void {
    $this->session->flash('a', 1);
    $this->session->flash('b', 2);
    $this->session->save();

    $second = reopen($this->session);
    $second->reflash();
    $second->save();

    $third = reopen($second);
    expect($third->get('a'))->toBe(1)->and($third->get('b'))->toBe(2);
    $third->keep('a');
    $third->save();

    $fourth = reopen($third);
    expect($fourth->get('a'))->toBe(1)->and($fourth->get('b'))->toBeNull();
});

it('guarda o input antigo em flash', function (): void {
    $this->session->flashInput(['name' => 'Ana', 'address' => ['city' => 'Porto']]);
    $this->session->save();

    $next = reopen($this->session);

    expect($next->getOldInput('name'))->toBe('Ana')
        ->and($next->getOldInput('address.city'))->toBe('Porto')
        ->and($next->getOldInput('x', 'd'))->toBe('d')
        ->and($next->getOldInput())->toBe(['name' => 'Ana', 'address' => ['city' => 'Porto']]);

    $next->save();
    expect(reopen($next)->getOldInput())->toBe([]);
});

it('token CSRF é estável e regenerável', function (): void {
    $token = $this->session->token();

    expect($token)->toHaveLength(40)
        ->and($this->session->token())->toBe($token);

    $this->session->regenerateToken();
    expect($this->session->token())->not->toBe($token);
});

it('regenerate() muda o id mantendo os dados; invalidate() apaga tudo', function (): void {
    $this->session->put('k', 'v');
    $this->session->save();
    $old = $this->session->id();

    $this->session->regenerate();
    expect($this->session->id())->not->toBe($old)->and($this->session->get('k'))->toBe('v');

    $before = $this->session->id();
    $this->session->save();
    expect($this->handler->storage)->toHaveKey($before);

    $this->session->regenerate(destroy: true);
    expect($this->handler->storage)->not->toHaveKey($before);

    $this->session->invalidate();
    expect($this->session->all())->toBe([]);
});

it('previousUrl', function (): void {
    expect($this->session->previousUrl())->toBeNull();

    $this->session->setPreviousUrl('http://localhost/a');
    expect($this->session->previousUrl())->toBe('http://localhost/a');
});

it('isDirty() acompanha as alterações', function (): void {
    expect($this->session->isDirty())->toBeFalse();

    $this->session->put('a', 1);
    expect($this->session->isDirty())->toBeTrue();

    $this->session->save();
    expect($this->session->isDirty())->toBeFalse();
});

it('FileSessionHandler lê, escreve, apaga e faz gc', function (): void {
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'slendie-sess-'.bin2hex(random_bytes(4));
    $handler = new FileSessionHandler($dir, 120);

    expect($handler->read('abc'))->toBe('')
        ->and($handler->write('abc', 'dados'))->toBeTrue()
        ->and($handler->read('abc'))->toBe('dados');

    touch($dir.DIRECTORY_SEPARATOR.'abc', time() - 3600);
    expect($handler->gc(60))->toBe(1)
        ->and($handler->read('abc'))->toBe('');

    $handler->write('def', 'x');
    expect($handler->destroy('def'))->toBeTrue()
        ->and($handler->read('def'))->toBe('');

    // expiradas por lifetime são ignoradas na leitura
    $short = new FileSessionHandler($dir, 1);
    $short->write('old', 'x');
    touch($dir.DIRECTORY_SEPARATOR.'old', time() - 600);
    expect($short->read('old'))->toBe('');

    // path traversal no id é neutralizado
    $handler->write('../evil', 'x');
    expect(is_file($dir.DIRECTORY_SEPARATOR.'evil'))->toBeTrue();

    array_map('unlink', glob($dir.DIRECTORY_SEPARATOR.'*') ?: []);
    rmdir($dir);
});
