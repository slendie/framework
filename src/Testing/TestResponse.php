<?php

declare(strict_types=1);

namespace Slendie\Testing;

use Closure;
use PHPUnit\Framework\Assert;
use Slendie\Http\Request;
use Slendie\Http\Response;
use Slendie\Http\ViewResponse;
use Slendie\Session\Session;
use Slendie\Support\Arr;
use Slendie\View\View;
use Throwable;

/**
 * Envolve uma Response com as asserções do Slendie.
 *
 * @property-read string $content
 *
 * @mixin Response
 */
class TestResponse
{
    /**
     * @param  Session|null  $session  retrato da sessão do cliente de teste no fim do pedido (só leitura)
     */
    public function __construct(
        public readonly Response $baseResponse,
        public readonly ?Request $request = null,
        protected ?Session $session = null,
        protected ?Throwable $exception = null,
    ) {
    }

    // -------------------------------------------------------------------------
    // Estado
    // -------------------------------------------------------------------------

    public function getContent(): string
    {
        return $this->baseResponse->getContent();
    }

    public function status(): int
    {
        return $this->baseResponse->getStatusCode();
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->baseResponse->headers();
    }

    public function exception(): ?Throwable
    {
        return $this->exception;
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'content' => $this->getContent(),
            'status' => $this->status(),
            default => throw new \LogicException("TestResponse não tem a propriedade [{$name}]."),
        };
    }

    /**
     * @param  array<array-key, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->baseResponse->{$method}(...$arguments);
    }

    // -------------------------------------------------------------------------
    // Estado HTTP
    // -------------------------------------------------------------------------

    public function assertStatus(int $status): static
    {
        Assert::assertSame($status, $this->status(), $this->failure("Expected response status code [{$status}] but received {$this->status()}."));

        return $this;
    }

    public function assertOk(): static
    {
        return $this->assertStatus(200);
    }

    public function assertCreated(): static
    {
        return $this->assertStatus(201);
    }

    public function assertNoContent(int $status = 204): static
    {
        $this->assertStatus($status);
        Assert::assertSame('', $this->getContent(), $this->failure('Response content is not empty.'));

        return $this;
    }

    public function assertNotFound(): static
    {
        return $this->assertStatus(404);
    }

    public function assertForbidden(): static
    {
        return $this->assertStatus(403);
    }

    public function assertUnauthorized(): static
    {
        return $this->assertStatus(401);
    }

    public function assertUnprocessable(): static
    {
        return $this->assertStatus(422);
    }

    public function assertMethodNotAllowed(): static
    {
        return $this->assertStatus(405);
    }

    public function assertSuccessful(): static
    {
        Assert::assertTrue($this->baseResponse->isSuccessful(), $this->failure("Expected a successful (2xx) response but received {$this->status()}."));

        return $this;
    }

    public function assertServerError(): static
    {
        Assert::assertTrue($this->baseResponse->isServerError(), $this->failure("Expected a server error (5xx) response but received {$this->status()}."));

        return $this;
    }

    public function assertRedirect(?string $uri = null): static
    {
        Assert::assertTrue($this->baseResponse->isRedirect(), $this->failure("Expected a redirect response but received {$this->status()}."));

        if ($uri !== null) {
            $this->assertLocation($uri);
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function assertRedirectToRoute(string $name, array $parameters = []): static
    {
        return $this->assertRedirect(route($name, $parameters));
    }

    public function assertRedirectContains(string $needle): static
    {
        Assert::assertTrue($this->baseResponse->isRedirect(), $this->failure("Expected a redirect response but received {$this->status()}."));
        Assert::assertStringContainsString($needle, (string) $this->baseResponse->getHeader('Location'), $this->failure("Redirect location does not contain [{$needle}]."));

        return $this;
    }

    public function assertLocation(string $uri): static
    {
        $location = (string) $this->baseResponse->getHeader('Location');

        Assert::assertTrue(
            rtrim($location, '/') === rtrim($uri, '/') || rtrim($location, '/') === rtrim($this->absolute($uri), '/'),
            $this->failure("Expected redirect location [{$uri}] but received [{$location}]."),
        );

        return $this;
    }

    // -------------------------------------------------------------------------
    // Headers e cookies
    // -------------------------------------------------------------------------

    public function assertHeader(string $name, ?string $value = null): static
    {
        Assert::assertTrue($this->baseResponse->hasHeader($name), "Header [{$name}] not present on response.");

        if ($value !== null) {
            Assert::assertSame($value, $this->baseResponse->getHeader($name), "Header [{$name}] was found, but value [{$this->baseResponse->getHeader($name)}] does not match [{$value}].");
        }

        return $this;
    }

    public function assertHeaderMissing(string $name): static
    {
        Assert::assertFalse($this->baseResponse->hasHeader($name), "Unexpected header [{$name}] is present on response.");

        return $this;
    }

    public function assertCookie(string $name, ?string $value = null): static
    {
        $cookie = $this->cookie($name);

        Assert::assertNotNull($cookie, "Cookie [{$name}] not present on response.");

        if ($value !== null) {
            Assert::assertSame($value, $cookie->value, "Cookie [{$name}] was found, but value [{$cookie->value}] does not match [{$value}].");
        }

        return $this;
    }

    /** Sem encriptação de cookies nesta versão, igual a assertCookie(). */
    public function assertPlainCookie(string $name, ?string $value = null): static
    {
        return $this->assertCookie($name, $value);
    }

    public function assertCookieMissing(string $name): static
    {
        Assert::assertNull($this->cookie($name), "Cookie [{$name}] is present on response.");

        return $this;
    }

    // -------------------------------------------------------------------------
    // Corpo
    // -------------------------------------------------------------------------

    /**
     * @param  string|list<string>  $value
     */
    public function assertSee(string|array $value, bool $escape = true): static
    {
        foreach ((array) $value as $item) {
            $needle = $escape ? $this->escape($item) : $item;

            Assert::assertStringContainsString($needle, $this->getContent(), $this->failure("Failed asserting that the response contains [{$item}]."));
        }

        return $this;
    }

    /**
     * @param  list<string>  $values
     */
    public function assertSeeInOrder(array $values, bool $escape = true): static
    {
        $position = 0;
        $content = $this->getContent();

        foreach ($values as $value) {
            $needle = $escape ? $this->escape($value) : $value;
            $found = $needle === '' ? $position : strpos($content, $needle, $position);

            Assert::assertNotFalse($found, $this->failure("Failed asserting that [{$value}] appears in the expected order."));

            $position = $found + strlen($needle);
        }

        return $this;
    }

    /**
     * @param  string|list<string>  $value
     */
    public function assertSeeText(string|array $value, bool $escape = true): static
    {
        $text = $this->text();

        foreach ((array) $value as $item) {
            Assert::assertStringContainsString($escape ? $this->escape($item) : $item, $text, $this->failure("Failed asserting that the response text contains [{$item}]."));
        }

        return $this;
    }

    /**
     * @param  string|list<string>  $value
     */
    public function assertDontSee(string|array $value, bool $escape = true): static
    {
        foreach ((array) $value as $item) {
            Assert::assertStringNotContainsString($escape ? $this->escape($item) : $item, $this->getContent(), $this->failure("Failed asserting that the response does not contain [{$item}]."));
        }

        return $this;
    }

    /**
     * @param  string|list<string>  $value
     */
    public function assertDontSeeText(string|array $value, bool $escape = true): static
    {
        $text = $this->text();

        foreach ((array) $value as $item) {
            Assert::assertStringNotContainsString($escape ? $this->escape($item) : $item, $text, $this->failure("Failed asserting that the response text does not contain [{$item}]."));
        }

        return $this;
    }

    public function assertContent(string $content): static
    {
        Assert::assertSame($content, $this->getContent(), $this->failure('Response content does not match.'));

        return $this;
    }

    // -------------------------------------------------------------------------
    // Views
    // -------------------------------------------------------------------------

    public function assertViewIs(string $name): static
    {
        Assert::assertSame($name, $this->view()->name(), $this->failure("Expected view [{$name}] but the response rendered [{$this->view()->name()}]."));

        return $this;
    }

    public function assertViewHas(string $key, mixed $value = null): static
    {
        $data = $this->view()->getData();

        Assert::assertTrue(Arr::has($data, $key), "Failed asserting that the view has the data [{$key}].");

        $actual = Arr::get($data, $key);

        if ($value instanceof Closure) {
            Assert::assertTrue($value($actual), "The view data [{$key}] did not pass the given callback.");
        } elseif ($value !== null) {
            Assert::assertEquals($value, $actual, "The view data [{$key}] does not match the expected value.");
        }

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $bindings  lista de chaves ou chave => valor
     */
    public function assertViewHasAll(array $bindings): static
    {
        foreach ($bindings as $key => $value) {
            is_int($key) ? $this->assertViewHas((string) $value) : $this->assertViewHas($key, $value);
        }

        return $this;
    }

    public function assertViewMissing(string $key): static
    {
        Assert::assertFalse(Arr::has($this->view()->getData(), $key), "Failed asserting that the view does not have the data [{$key}].");

        return $this;
    }

    public function viewData(string $key): mixed
    {
        return Arr::get($this->view()->getData(), $key);
    }

    // -------------------------------------------------------------------------
    // JSON
    // -------------------------------------------------------------------------

    /**
     * @return ($key is null ? mixed : mixed)
     */
    public function json(?string $key = null): mixed
    {
        $decoded = $this->decodeResponseJson();

        return $key === null ? $decoded : (is_array($decoded) ? Arr::get($decoded, $key) : null);
    }

    public function decodeResponseJson(): mixed
    {
        $decoded = json_decode($this->getContent(), true);

        Assert::assertTrue(
            json_last_error() === JSON_ERROR_NONE,
            $this->failure('Response is not valid JSON: '.json_last_error_msg().'.'),
        );

        return $decoded;
    }

    /**
     * Subconjunto: o JSON da resposta tem de conter (recursivamente) os dados indicados.
     *
     * @param  array<array-key, mixed>  $data
     */
    public function assertJson(array $data, bool $strict = false): static
    {
        $actual = $this->decodeResponseJson();

        Assert::assertIsArray($actual, $this->failure('Response JSON is not an object or array.'));
        Assert::assertTrue(
            $this->isSubset($data, $actual, $strict),
            $this->failure("Unable to find JSON:\n\n".$this->pretty($data)."\n\nwithin response JSON:\n\n".$this->pretty($actual).'.'),
        );

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function assertExactJson(array $data): static
    {
        Assert::assertSame(
            $this->pretty($this->canonicalize($data)),
            $this->pretty($this->canonicalize($this->decodeResponseJson())),
            $this->failure('Response JSON does not match exactly.'),
        );

        return $this;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function assertJsonFragment(array $data): static
    {
        $haystack = $this->compact($this->decodeResponseJson());

        foreach ($data as $key => $value) {
            $needle = trim((string) json_encode([$key => $value], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), '{}');

            Assert::assertStringContainsString($needle, $haystack, $this->failure("Unable to find JSON fragment [{$needle}] within the response JSON."));
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function assertJsonMissing(array $data): static
    {
        $haystack = $this->compact($this->decodeResponseJson());

        foreach ($data as $key => $value) {
            $needle = trim((string) json_encode([$key => $value], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), '{}');

            Assert::assertStringNotContainsString($needle, $haystack, $this->failure("Found unexpected JSON fragment [{$needle}] within the response JSON."));
        }

        return $this;
    }

    public function assertJsonPath(string $path, mixed $expected): static
    {
        $actual = $this->json($path);

        if ($expected instanceof Closure) {
            Assert::assertTrue($expected($actual), $this->failure("JSON path [{$path}] did not pass the given callback."));

            return $this;
        }

        Assert::assertEquals($expected, $actual, $this->failure("JSON path [{$path}] does not match the expected value."));

        return $this;
    }

    public function assertJsonCount(int $count, ?string $path = null): static
    {
        $value = $this->json($path);

        Assert::assertIsArray($value, $this->failure('The JSON '.($path === null ? 'response' : "path [{$path}]").' is not an array or object.'));
        Assert::assertCount($count, $value, $this->failure('The JSON '.($path === null ? 'response' : "path [{$path}]").' does not contain the expected number of items.'));

        return $this;
    }

    /**
     * `'*'` percorre todos os elementos de uma lista. Lista de chaves => têm de existir; chave => estrutura => recursivo.
     *
     * @param  array<array-key, mixed>  $structure
     * @param  array<array-key, mixed>|null  $responseData
     */
    public function assertJsonStructure(array $structure, ?array $responseData = null): static
    {
        $data = $responseData ?? $this->decodeResponseJson();

        Assert::assertIsArray($data, $this->failure('Response JSON is not an object or array.'));

        $this->checkStructure($structure, $data);

        return $this;
    }

    /**
     * @param  string|array<array-key, mixed>  $errors  chaves, ou chave => mensagem(ns)
     */
    public function assertJsonValidationErrors(string|array $errors, string $responseKey = 'errors'): static
    {
        $bag = $this->json($responseKey);

        Assert::assertIsArray($bag, $this->failure("Expected validation errors under [{$responseKey}] but none were found."));

        foreach (is_array($errors) ? $errors : [$errors] as $key => $expected) {
            if (is_int($key)) {
                Assert::assertArrayHasKey((string) $expected, $bag, $this->failure("Failed to find a validation error in the response for key: '{$expected}'."));

                continue;
            }

            Assert::assertArrayHasKey($key, $bag, $this->failure("Failed to find a validation error in the response for key: '{$key}'."));

            foreach ((array) $expected as $message) {
                Assert::assertContains($message, (array) $bag[$key], $this->failure("Failed to find the validation error [{$message}] for key '{$key}'."));
            }
        }

        return $this;
    }

    /**
     * @param  string|list<string>|null  $keys  null = não pode haver erros nenhuns
     */
    public function assertJsonMissingValidationErrors(string|array|null $keys = null, string $responseKey = 'errors'): static
    {
        $bag = $this->json($responseKey);
        $bag = is_array($bag) ? $bag : [];

        if ($keys === null) {
            Assert::assertSame([], $bag, $this->failure('Response has unexpected validation errors.'));

            return $this;
        }

        foreach ((array) $keys as $key) {
            Assert::assertArrayNotHasKey($key, $bag, $this->failure("Response has an unexpected validation error for key: '{$key}'."));
        }

        return $this;
    }

    public function assertJsonIsArray(): static
    {
        $decoded = $this->decodeResponseJson();

        Assert::assertTrue(is_array($decoded) && array_is_list($decoded), $this->failure('The response JSON is not an array.'));

        return $this;
    }

    public function assertJsonIsObject(): static
    {
        $decoded = $this->decodeResponseJson();

        Assert::assertTrue(is_array($decoded) && ($decoded === [] ? str_starts_with(trim($this->getContent()), '{') : ! array_is_list($decoded)), $this->failure('The response JSON is not an object.'));

        return $this;
    }

    // -------------------------------------------------------------------------
    // Sessão
    // -------------------------------------------------------------------------

    public function assertSessionHas(string $key, mixed $value = null): static
    {
        $session = $this->sessionOrFail();

        Assert::assertTrue($session->has($key), "Session is missing expected key [{$key}].");

        if ($value instanceof Closure) {
            Assert::assertTrue($value($session->get($key)), "Session value for [{$key}] did not pass the given callback.");
        } elseif ($value !== null) {
            Assert::assertEquals($value, $session->get($key), "Session value for [{$key}] does not match.");
        }

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $bindings  lista de chaves ou chave => valor
     */
    public function assertSessionHasAll(array $bindings): static
    {
        foreach ($bindings as $key => $value) {
            is_int($key) ? $this->assertSessionHas((string) $value) : $this->assertSessionHas($key, $value);
        }

        return $this;
    }

    public function assertSessionMissing(string $key): static
    {
        Assert::assertFalse($this->sessionOrFail()->has($key), "Session has unexpected key [{$key}].");

        return $this;
    }

    public function assertSessionHasInput(string $key, mixed $value = null): static
    {
        $session = $this->sessionOrFail();
        $old = $session->getOldInput();
        $old = is_array($old) ? $old : [];

        Assert::assertTrue(Arr::has($old, $key), "Session is missing the old input [{$key}].");

        if ($value instanceof Closure) {
            Assert::assertTrue($value(Arr::get($old, $key)), "Old input [{$key}] did not pass the given callback.");
        } elseif ($value !== null) {
            Assert::assertEquals($value, Arr::get($old, $key), "Old input [{$key}] does not match.");
        }

        return $this;
    }

    /**
     * Sem chaves: tem de existir pelo menos um erro. Com lista: essas chaves; com chave => mensagem: também a mensagem.
     *
     * @param  string|array<array-key, mixed>  $keys
     */
    public function assertSessionHasErrors(string|array $keys = [], ?string $format = null, string $bag = 'default'): static
    {
        $errors = $this->sessionErrors($bag);

        if ($keys === [] || $keys === '') {
            Assert::assertNotSame([], $errors, "Session does not contain errors in the [{$bag}] bag.");

            return $this;
        }

        foreach (is_array($keys) ? $keys : [$keys] as $key => $expected) {
            if (is_int($key)) {
                Assert::assertArrayHasKey((string) $expected, $errors, "Session is missing the error for [{$expected}]. Errors: ".$this->pretty($errors));

                continue;
            }

            Assert::assertArrayHasKey($key, $errors, "Session is missing the error for [{$key}]. Errors: ".$this->pretty($errors));

            foreach ((array) $expected as $message) {
                $actual = $this->formatMessages((array) $errors[$key], $key, $format);

                Assert::assertContains($message, $actual, "Session error [{$key}] does not contain [{$message}]. Actual: ".$this->pretty($actual));
            }
        }

        return $this;
    }

    public function assertSessionHasNoErrors(): static
    {
        $all = $this->sessionOrFail()->get('errors', []);

        Assert::assertTrue(
            ! is_array($all) || array_filter($all) === [],
            'Session has unexpected errors: '.$this->pretty($all),
        );

        return $this;
    }

    /**
     * @param  string|array<array-key, mixed>  $keys
     */
    public function assertSessionDoesntHaveErrors(string|array $keys = [], ?string $format = null, string $bag = 'default'): static
    {
        $errors = $this->sessionErrors($bag);

        if ($keys === [] || $keys === '') {
            Assert::assertSame([], $errors, 'Session has unexpected errors: '.$this->pretty($errors));

            return $this;
        }

        foreach (is_array($keys) ? $keys : [$keys] as $key => $expected) {
            if (is_int($key)) {
                Assert::assertArrayNotHasKey((string) $expected, $errors, "Session has an unexpected error for [{$expected}].");

                continue;
            }

            foreach ((array) $expected as $message) {
                Assert::assertNotContains($message, $this->formatMessages((array) ($errors[$key] ?? []), $key, $format), "Session has the unexpected error [{$message}] for [{$key}].");
            }
        }

        return $this;
    }

    /**
     * Valida (sessão ou JSON): sem chaves não pode haver erros; com chaves essas não podem ter erro.
     *
     * @param  string|list<string>|null  $keys
     */
    public function assertValid(string|array|null $keys = null, string $bag = 'default', string $responseKey = 'errors'): static
    {
        $errors = $this->allErrors($bag, $responseKey);

        if ($keys === null) {
            Assert::assertSame([], $errors, 'Response has unexpected validation errors: '.$this->pretty($errors));

            return $this;
        }

        foreach ((array) $keys as $key) {
            Assert::assertArrayNotHasKey($key, $errors, "Response has an unexpected validation error for [{$key}]: ".$this->pretty($errors[$key] ?? []));
        }

        return $this;
    }

    /**
     * @param  string|array<array-key, mixed>  $keys  chaves, ou chave => mensagem(ns)
     */
    public function assertInvalid(string|array $keys, string $bag = 'default', string $responseKey = 'errors'): static
    {
        $errors = $this->allErrors($bag, $responseKey);

        foreach (is_array($keys) ? $keys : [$keys] as $key => $expected) {
            if (is_int($key)) {
                Assert::assertArrayHasKey((string) $expected, $errors, "Expected a validation error for [{$expected}]. Errors: ".$this->pretty($errors));

                continue;
            }

            Assert::assertArrayHasKey($key, $errors, "Expected a validation error for [{$key}]. Errors: ".$this->pretty($errors));

            foreach ((array) $expected as $message) {
                Assert::assertContains($message, (array) $errors[$key], "Validation error [{$key}] does not contain [{$message}].");
            }
        }

        return $this;
    }

    // -------------------------------------------------------------------------
    // Depuração
    // -------------------------------------------------------------------------

    public function dump(?string $key = null): static
    {
        $content = $this->getContent();
        $decoded = json_decode($content, true);

        if ($key !== null && is_array($decoded)) {
            $decoded = Arr::get($decoded, $key);
            echo is_scalar($decoded) ? (string) $decoded : $this->pretty($decoded), PHP_EOL;

            return $this;
        }

        echo $key === null && is_array($decoded) ? $this->pretty($decoded) : $content, PHP_EOL;

        return $this;
    }

    public function dumpHeaders(): static
    {
        echo $this->pretty($this->headers()), PHP_EOL;

        return $this;
    }

    public function dumpSession(): static
    {
        echo $this->pretty($this->session?->all() ?? []), PHP_EOL;

        return $this;
    }

    public function dd(): never
    {
        $this->dump();

        exit(1);
    }

    // -------------------------------------------------------------------------
    // Internos
    // -------------------------------------------------------------------------

    protected function view(): View
    {
        Assert::assertInstanceOf(ViewResponse::class, $this->baseResponse, $this->failure('The response is not a view response.'));

        /** @var ViewResponse $response */
        $response = $this->baseResponse;
        $view = $response->getView();

        Assert::assertInstanceOf(View::class, $view, $this->failure('The response does not render a Slendie view.'));

        return $view;
    }

    protected function sessionOrFail(): Session
    {
        $session = $this->session;

        Assert::assertNotNull($session, 'No session is available: the request did not go through the "web" middleware group.');

        return $session;
    }

    /**
     * @return array<string, list<string>>
     */
    protected function sessionErrors(string $bag): array
    {
        $all = $this->sessionOrFail()->get('errors', []);
        $errors = is_array($all) && is_array($all[$bag] ?? null) ? $all[$bag] : [];

        /** @var array<string, list<string>> $errors */
        return $errors;
    }

    /**
     * Erros da sessão (redirect) ou do JSON (422), o que existir.
     *
     * @return array<string, mixed>
     */
    protected function allErrors(string $bag, string $responseKey): array
    {
        $fromSession = [];

        if (($session = $this->session) !== null) {
            $all = $session->get('errors', []);
            $fromSession = is_array($all) && is_array($all[$bag] ?? null) ? $all[$bag] : [];
        }

        $decoded = json_decode($this->getContent(), true);
        $fromJson = is_array($decoded) && is_array($decoded[$responseKey] ?? null) ? $decoded[$responseKey] : [];

        /** @var array<string, mixed> */
        return [...$fromSession, ...$fromJson];
    }

    /**
     * Aplica o formato (`:message`, `:key`) às mensagens reais antes de as comparar com as esperadas.
     *
     * @param  array<array-key, mixed>  $messages
     * @return list<string>
     */
    protected function formatMessages(array $messages, string $key, ?string $format): array
    {
        return array_values(array_map(
            static fn (mixed $message): string => $format === null
                ? (string) $message
                : str_replace([':message', ':key'], [(string) $message, $key], $format),
            $messages,
        ));
    }

    protected function cookie(string $name): ?\Slendie\Http\Cookie
    {
        foreach ($this->baseResponse->cookies() as $cookie) {
            if ($cookie->name === $name) {
                return $cookie;
            }
        }

        return null;
    }

    protected function absolute(string $uri): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $uri) === 1) {
            return $uri;
        }

        $root = $this->request?->root() ?? 'http://localhost';

        return rtrim($root, '/').'/'.ltrim($uri, '/');
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8', true);
    }

    protected function text(): string
    {
        return strip_tags($this->getContent());
    }

    /** Mensagem de falha com a excepção capturada (se houver) e o início do corpo. */
    protected function failure(string $message): string
    {
        if ($this->exception !== null) {
            $message .= "\n\nThe following exception occurred during the request:\n\n"
                .$this->exception::class.': '.$this->exception->getMessage()
                .' in '.$this->exception->getFile().':'.$this->exception->getLine();
        }

        $body = $this->getContent();

        if ($body !== '') {
            $message .= "\n\nResponse body ("
                .(strlen($body) > 500 ? 'first 500 of '.strlen($body).' chars' : strlen($body).' chars')
                ."):\n".mb_strcut($body, 0, 500);
        }

        return $message;
    }

    /**
     * @param  array<array-key, mixed>  $structure
     * @param  array<array-key, mixed>  $data
     */
    protected function checkStructure(array $structure, array $data): void
    {
        foreach ($structure as $key => $value) {
            if ($key === '*' && is_array($value)) {
                foreach ($data as $item) {
                    Assert::assertIsArray($item, $this->failure('Expected a list of objects while matching the JSON structure.'));
                    $this->checkStructure($value, $item);
                }

                continue;
            }

            if (is_array($value)) {
                Assert::assertArrayHasKey($key, $data, $this->failure("Failed asserting that the JSON has the key [{$key}]."));
                Assert::assertIsArray($data[$key], $this->failure("Failed asserting that the JSON value of [{$key}] is an array or object."));
                $this->checkStructure($value, $data[$key]);

                continue;
            }

            Assert::assertArrayHasKey($value, $data, $this->failure("Failed asserting that the JSON has the key [{$value}]."));
        }
    }

    /**
     * @param  array<array-key, mixed>  $expected
     * @param  array<array-key, mixed>  $actual
     */
    protected function isSubset(array $expected, array $actual, bool $strict): bool
    {
        foreach ($expected as $key => $value) {
            if (! array_key_exists($key, $actual)) {
                return false;
            }

            if (is_array($value)) {
                if (! is_array($actual[$key]) || ! $this->isSubset($value, $actual[$key], $strict)) {
                    return false;
                }

                continue;
            }

            if ($strict ? $value !== $actual[$key] : $value != $actual[$key]) {
                return false;
            }
        }

        return true;
    }

    protected function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map($this->canonicalize(...), $value);

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    protected function compact(mixed $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function pretty(mixed $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
