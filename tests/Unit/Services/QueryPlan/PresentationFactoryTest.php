<?php

declare(strict_types=1);

namespace Tests\Unit\Services\QueryPlan;

use App\Services\QueryPlan\Derive;
use App\Services\QueryPlan\DeriveOp;
use App\Services\QueryPlan\DisplayHint;
use App\Services\QueryPlan\Presentation;
use App\Services\QueryPlan\PresentationFactory;
use App\Services\QueryPlan\RefusalReason;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PresentationFactoryTest extends TestCase
{
    public function test_builds_a_plain_presentation_pointing_at_a_query(): void
    {
        $presentation = (new PresentationFactory())->fromArray([
            'resultRef' => 'q2',
            'display' => 'count',
            'derive' => null,
            'explanation' => 'How many.',
        ], ['q1', 'q2']);

        self::assertSame('q2', $presentation->resultRef);
        self::assertSame(DisplayHint::Count, $presentation->display);
        self::assertFalse($presentation->isDerived());
    }

    public function test_parses_trimmed_deduplicated_and_capped_follow_ups(): void
    {
        $presentation = (new PresentationFactory())->fromArray([
            'resultRef' => 'q1',
            'display' => 'count',
            'derive' => null,
            'explanation' => 'How many.',
            'followUps' => [
                '  Porsche 911 per year ',
                '',
                'Porsche 911 per year',
                'Average power of the Porsche 911',
                'Electric Porsche 911s',
                'Fourth question',
            ],
        ], ['q1']);

        self::assertSame(
            ['Porsche 911 per year', 'Average power of the Porsche 911', 'Electric Porsche 911s'],
            $presentation->followUps,
        );
    }

    public function test_follow_ups_default_to_empty_when_missing(): void
    {
        $presentation = (new PresentationFactory())->fromArray([
            'resultRef' => 'q1',
            'display' => 'count',
            'derive' => null,
            'explanation' => 'How many.',
        ], ['q1']);

        self::assertSame([], $presentation->followUps);
    }

    public function test_rejects_a_result_ref_outside_the_program(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PresentationFactory())->fromArray([
            'resultRef' => 'q9', 'display' => 'count', 'derive' => null, 'explanation' => 'x',
        ], ['q1']);
    }

    public function test_builds_a_percentage_derive(): void
    {
        $presentation = (new PresentationFactory())->fromArray([
            'resultRef' => Presentation::DERIVED_REF,
            'display' => 'count',
            'derive' => ['op' => 'percentage', 'numerator' => 'q1', 'denominator' => 'q2'],
            'explanation' => 'Share.',
        ], ['q1', 'q2']);

        self::assertTrue($presentation->isDerived());

        $derive = $presentation->derive;
        self::assertInstanceOf(Derive::class, $derive);

        /** @var Derive $derive */
        self::assertSame(DeriveOp::Percentage, $derive->op);
        self::assertSame('q1', $derive->numerator);
        self::assertSame('q2', $derive->denominator);
    }

    public function test_a_binary_derive_operand_tolerates_a_trailing_column_reference(): void
    {
        // The model commonly writes "q1.n"/"q2.electric_count"; only the query id is needed, so the
        // trailing ".field" is stripped rather than rejected.
        $presentation = (new PresentationFactory())->fromArray([
            'resultRef' => Presentation::DERIVED_REF,
            'display' => 'count',
            'derive' => ['op' => 'percentage', 'numerator' => 'q1.electric_count', 'denominator' => 'q2.n'],
            'explanation' => 'Share.',
        ], ['q1', 'q2']);

        $derive = $presentation->derive;
        self::assertInstanceOf(Derive::class, $derive);

        /** @var Derive $derive */
        self::assertSame('q1', $derive->numerator);
        self::assertSame('q2', $derive->denominator);
    }

    public function test_a_group_share_source_tolerates_a_trailing_column_reference(): void
    {
        $presentation = (new PresentationFactory())->fromArray([
            'resultRef' => Presentation::DERIVED_REF,
            'display' => 'count',
            'derive' => [
                'op' => 'groupShare', 'source' => 'q1.n',
                'selectorColumn' => 'PrimaryColor', 'selectorValue' => 'WIT',
            ],
            'explanation' => 'White share.',
        ], ['q1']);

        $derive = $presentation->derive;
        self::assertInstanceOf(Derive::class, $derive);

        /** @var Derive $derive */
        self::assertSame('q1', $derive->source);
    }

    public function test_a_derive_operand_with_an_unknown_query_id_is_still_rejected(): void
    {
        // Stripping the column must not mask a genuinely wrong query id.
        $this->expectException(InvalidArgumentException::class);

        (new PresentationFactory())->fromArray([
            'resultRef' => Presentation::DERIVED_REF,
            'display' => 'count',
            'derive' => ['op' => 'ratio', 'numerator' => 'q1.n', 'denominator' => 'q9.n'],
            'explanation' => 'x',
        ], ['q1', 'q2']);
    }

    public function test_a_derive_requires_the_derived_result_ref(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PresentationFactory())->fromArray([
            'resultRef' => 'q1',
            'display' => 'count',
            'derive' => ['op' => 'percentage', 'numerator' => 'q1', 'denominator' => 'q2'],
            'explanation' => 'x',
        ], ['q1', 'q2']);
    }

    public function test_rejects_a_derive_operand_outside_the_program(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PresentationFactory())->fromArray([
            'resultRef' => Presentation::DERIVED_REF,
            'display' => 'count',
            'derive' => ['op' => 'ratio', 'numerator' => 'q1', 'denominator' => 'q9'],
            'explanation' => 'x',
        ], ['q1', 'q2']);
    }

    public function test_builds_a_group_share_derive(): void
    {
        $presentation = (new PresentationFactory())->fromArray([
            'resultRef' => Presentation::DERIVED_REF,
            'display' => 'count',
            'derive' => [
                'op' => 'groupShare', 'source' => 'q1',
                'selectorColumn' => 'PrimaryColor', 'selectorValue' => 'GEEL',
            ],
            'explanation' => 'Yellow share.',
        ], ['q1']);

        $derive = $presentation->derive;
        self::assertInstanceOf(Derive::class, $derive);

        /** @var Derive $derive */
        self::assertSame(DeriveOp::GroupShare, $derive->op);
        self::assertSame('q1', $derive->source);
        self::assertSame('PrimaryColor', $derive->selectorColumn);
        self::assertSame('GEEL', $derive->selectorValue);
    }

    public function test_normalises_a_pink_group_share_selector_to_the_live_rdw_value(): void
    {
        $presentation = (new PresentationFactory())->fromArray([
            'resultRef' => Presentation::DERIVED_REF,
            'display' => 'count',
            'derive' => [
                'op' => 'groupShare', 'source' => 'q1',
                'selectorColumn' => 'PrimaryColor', 'selectorValue' => 'ROZE',
            ],
            'explanation' => 'Pink share.',
        ], ['q1']);

        $derive = $presentation->derive;
        self::assertInstanceOf(Derive::class, $derive);

        /** @var Derive $derive */
        self::assertSame('ROSE', $derive->selectorValue);
    }

    public function test_group_share_alias_normalisation_is_scoped_to_colour_selectors_only(): void
    {
        $presentation = (new PresentationFactory())->fromArray([
            'resultRef' => Presentation::DERIVED_REF,
            'display' => 'count',
            'derive' => [
                'op' => 'groupShare', 'source' => 'q1',
                'selectorColumn' => 'Brand', 'selectorValue' => 'ROZE',
            ],
            'explanation' => 'Scoped alias test.',
        ], ['q1']);

        $derive = $presentation->derive;
        self::assertInstanceOf(Derive::class, $derive);

        /** @var Derive $derive */
        self::assertSame('ROZE', $derive->selectorValue);
    }

    public function test_group_share_requires_a_selector(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PresentationFactory())->fromArray([
            'resultRef' => Presentation::DERIVED_REF,
            'display' => 'count',
            'derive' => ['op' => 'groupShare', 'source' => 'q1', 'selectorColumn' => '', 'selectorValue' => ''],
            'explanation' => 'x',
        ], ['q1']);
    }

    public function test_parses_a_refusal_with_reason_and_capped_suggestions(): void
    {
        // A refusal program carries no real result, so resultRef/derive are not validated against
        // the query ids; the reason + alternative questions come through, trimmed and capped at 3.
        $presentation = (new PresentationFactory())->fromArray([
            'resultRef' => 'q1',
            'display' => 'unsupported',
            'derive' => null,
            'refusal' => [
                'reason' => 'no_such_data',
                'suggestions' => ['  How many electric cars? ', '', 'Average CO2?', 'Most common colour?', 'Fourth?'],
            ],
            'explanation' => 'The registry does not record the driver.',
        ], ['q1']);

        self::assertSame(DisplayHint::Unsupported, $presentation->display);
        self::assertNotNull($presentation->refusal);
        self::assertSame(RefusalReason::NoSuchData, $presentation->refusal->reason);
        self::assertSame(
            ['How many electric cars?', 'Average CO2?', 'Most common colour?'],
            $presentation->refusal->suggestions,
        );
    }

    public function test_an_unsupported_display_without_a_refusal_defaults_to_out_of_scope(): void
    {
        $presentation = (new PresentationFactory())->fromArray([
            'resultRef' => 'q1',
            'display' => 'unsupported',
            'derive' => null,
            'refusal' => null,
            'explanation' => 'Outside the scope of the vehicle registry.',
        ], ['q1']);

        self::assertNotNull($presentation->refusal);
        self::assertSame(RefusalReason::OutOfScope, $presentation->refusal->reason);
        self::assertSame([], $presentation->refusal->suggestions);
    }

    public function test_rejects_an_unknown_refusal_reason(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PresentationFactory())->fromArray([
            'resultRef' => 'q1',
            'display' => 'unsupported',
            'derive' => null,
            'refusal' => ['reason' => 'because_i_said_so', 'suggestions' => []],
            'explanation' => 'x',
        ], ['q1']);
    }

    public function test_rejects_an_invalid_op_and_display(): void
    {
        $factory = new PresentationFactory();

        try {
            $factory->fromArray([
                'resultRef' => Presentation::DERIVED_REF, 'display' => 'count',
                'derive' => ['op' => 'nope', 'numerator' => 'q1', 'denominator' => 'q2'], 'explanation' => 'x',
            ], ['q1', 'q2']);
            self::fail('Expected an invalid op to throw.');
        } catch (InvalidArgumentException) {
            // expected
        }

        $this->expectException(InvalidArgumentException::class);
        $factory->fromArray([
            'resultRef' => 'q1', 'display' => 'nonsense', 'derive' => null, 'explanation' => 'x',
        ], ['q1']);
    }
}
