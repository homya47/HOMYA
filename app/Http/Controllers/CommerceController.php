<?php

namespace App\Http\Controllers;

use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

abstract class CommerceController extends Controller
{
    public function callAction($method, $parameters)
    {
        try {
            return $this->{$method}(...array_values($parameters));
        } catch (ValidationException|HttpExceptionInterface $error) {
            throw $error;
        } catch (Throwable) {
            // Catch here, before Laravel's routing pipeline can report/render a private exception.
            return response()->view(request()->routeIs('returns.*') ? 'returns.unavailable' : 'commerce.unavailable', [], 503);
        }
    }
}
