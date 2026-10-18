<?php

declare(strict_types=1);

namespace App\Providers;

use Spatie\LaravelTypeScriptTransformer\LaravelData\LaravelDataTypeScriptTransformerExtension;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider as BaseTypeScriptTransformerServiceProvider;
use Spatie\TypeScriptTransformer\Formatters\PrettierFormatter;
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;

class TypeScriptTransformerServiceProvider extends BaseTypeScriptTransformerServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $config
            ->transformer(AttributedClassTransformer::class)
            ->transformer(EnumTransformer::class)
            ->transformDirectories(app_path())
            ->transformDirectories(base_path('src/Modules'))
            ->transformDirectories(base_path('src/Shared'))
            ->writer(new GlobalNamespaceWriter('generated.d.ts'))
            ->formatter(PrettierFormatter::class)
            // Registers the Spatie Data transformer, which is what turns a
            // `Spatie\LaravelData\Data` subclass into a TypeScript type. Without
            // it `generated.d.ts` only ever carried enums and every Data shape
            // had to be hand-written in TypeScript — the drift the frontend rules
            // ban. It must come last: the extension REPLACES
            // AttributedClassTransformer with the Laravel flavour, so that
            // transformer has to already be on the list when it runs.
            ->extension(new LaravelDataTypeScriptTransformerExtension);
    }
}
