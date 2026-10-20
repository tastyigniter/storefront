<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Concerns;

use Closure;
use Igniter\Flame\Exception\ApplicationException;
use Igniter\Flame\Exception\FlashException;
use Igniter\Storefront\Storefront;
use Illuminate\Validation\ValidationException;
use Throwable;

trait RedirectsFailures
{
    protected function attempt(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (ApplicationException|FlashException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['form' => Storefront::lang('alert.something_wrong')]);
        }
    }
}
