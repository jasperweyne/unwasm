<?php

/*
 * Copyright 2021-2022 Jasper Weyne
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
*/

declare(strict_types=1);

namespace UnWasm\Compiler\Node\Type;

/**
 * Describes a range of integer values.
 */
class Limits
{
    /** @var int Inclusive minimum of the range */
    public $minimum;

    /** @var ?int Inclusive maximum of the range */
    public $maximum;

    public function __construct(int $minimum, ?int $maximum = null)
    {
        $this->minimum = $minimum;
        $this->maximum = $maximum;

        // todo: validate that minimum is smaller or equal to maximum
    }

    public function inRange(int $k): bool
    {
        return $this->minimum <= $k && (!$this->maximum || $k <= $this->maximum);
    }
}
