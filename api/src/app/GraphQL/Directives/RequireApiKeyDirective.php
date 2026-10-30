<?php

namespace App\GraphQL\Directives;

use GraphQL\Error\Error;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

// The GraphQL version of the RequireApiKey middleware: put @requireApiKey on a field
// in schema.graphql and that field only runs when the right X-API-KEY header is sent.
class RequireApiKeyDirective extends BaseDirective implements FieldMiddleware
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"Only allow this field when the request has a valid X-API-KEY header."
directive @requireApiKey on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->wrapResolver(fn (callable $resolver) => function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver) {
            $expected = config('api.key');
            $given = (string) $context->request()?->header('X-API-KEY');

            // GraphQL answers HTTP 200 even for errors; the problem is reported in the "errors" list.
            if (! $expected || ! hash_equals($expected, $given)) {
                throw new Error('Missing or invalid X-API-KEY header.');
            }

            return $resolver($root, $args, $context, $resolveInfo);
        });
    }
}
