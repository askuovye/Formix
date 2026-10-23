<?php

declare(strict_types=1);

namespace App\Validation;

use App\Enums\RuleType;
use App\Validation\Rules\BlocklistRule;
use App\Validation\Rules\CepRule;
use App\Validation\Rules\CnpjRule;
use App\Validation\Rules\CpfRule;
use App\Validation\Rules\MaxLengthRule;
use App\Validation\Rules\MinDistinctCharsRule;
use App\Validation\Rules\MinLengthRule;
use App\Validation\Rules\MinWordsRule;
use App\Validation\Rules\NoOnlySymbolsRule;
use App\Validation\Rules\NoRepeatedCharsRule;
use App\Validation\Rules\NoSequencesRule;
use App\Validation\Rules\PhoneBrRule;
use Illuminate\Contracts\Container\Container;

/**
 * Liga cada RuleType à sua implementação. O `match` é exaustivo: um caso novo
 * no enum sem implementação não compila na análise estática (Larastan).
 */
final class RuleRegistry
{
    public function __construct(private readonly Container $container) {}

    public function for(RuleType $type): AnswerRule
    {
        return $this->container->make(match ($type) {
            RuleType::MinLength => MinLengthRule::class,
            RuleType::MaxLength => MaxLengthRule::class,
            RuleType::MinWords => MinWordsRule::class,
            RuleType::MinDistinctChars => MinDistinctCharsRule::class,
            RuleType::NoRepeatedChars => NoRepeatedCharsRule::class,
            RuleType::NoSequences => NoSequencesRule::class,
            RuleType::NoOnlySymbols => NoOnlySymbolsRule::class,
            RuleType::Blocklist => BlocklistRule::class,
            RuleType::Cpf => CpfRule::class,
            RuleType::Cnpj => CnpjRule::class,
            RuleType::PhoneBr => PhoneBrRule::class,
            RuleType::Cep => CepRule::class,
        });
    }
}
