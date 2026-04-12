<?php

namespace Akindutire\Authorization\Attributes;

use Akindutire\Authorization\Attributes\Interfaces\SubjectValueInterface;

/**
 * Attribute to mark a parameter that contains the subject identifier
 *
 * Usage:
 * public function update(#[SubjectValue('member_id')] Request $request) {}
 *
 * The middleware will extract $request->member_id to use as the subject lookup value
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class SubjectValue implements SubjectValueInterface
{
    /**
     * @param string $value The key to extract from the parameter (e.g., 'member_id', 'user_id')
     */
    public function __construct(public readonly string $value)
    {
    }

    /**
     * Get the key used to extract the subject value
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }
}
