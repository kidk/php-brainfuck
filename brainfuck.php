<?php

/*
The MIT License (MIT)

Copyright (c) 2015 Samuel Vandamme

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

*/

// Set to TRUE to output debug information after every OP
define('DEBUG', false);

// Convert sequential +/-/</> commands to shorter custom op. Provides a small speed improvement
define('PREOP_OPERATIONS', true);

/*
 * Retrieve script
 */
if (!isset($argv[1]) || !file_exists($argv[1])) {
    echo "Please supply script as first parameter";
    exit(0);
}
$code = file_get_contents($argv[1]);
$code = ' '.preg_replace('/[^\+\-\>\<\.\[\]]/', '', trim($code));

/*
 * Brainfuck interpreter
 */
$ip = 0; // Instruction pointer

// Caches for loop start and end
$loopStart = [];
$loopEnd = [];

// Preoptimize loops
if (PREOP_OPERATIONS) {
    foreach(['+', '-', '>', '<'] as $op) {
        preg_match_all('/['.$op.']{2,9}/', $code, $matches);
        arsort($matches[0]);
        $matches = array_unique($matches[0]);

        foreach($matches as $match) {
            $code = str_replace($match, '!'.$op.strlen($match), $code);
        }
    }
}

$code = trim($code);
while ($ip < strlen($code)) {
    // Operations
    $op = $code[$ip];
    switch ($op) {
        case '[':
            $count = 0;
            $cip = $ip + 1;
            // Find end of loop, ignoring internal loops
            while ($cip < strlen($code) && ($count > 0 || $code[$cip] !== ']')) {
                $cop = $code[$cip];
                if ($cop == '[') {
                    $count++;
                }
                if ($cop == ']') {
                    $count--;
                }

                $cip++;
            }

            $loopStart[$cip] = $ip;
            $loopEnd[$ip] = $cip;
            break;
    }

    $ip++;
}


// Main loop
$instructions = 0;
$heap = []; // Data heap
$ip = 0; // Instruction pointer
$dp = 0; // Data pointer
$count = 0;
$output = '';

while ($ip < strlen($code)) {
    $instructions++;
    $op = $code[$ip];

    // Debug statements, show statements before operation
    if (DEBUG) {
        echo "$instructions \t $op \t".PHP_EOL;
        echo json_encode($heap).PHP_EOL;
    }

    // Set unset heap data to avoid PHP exceptions
    if (!isset($heap[$dp])) {
        $heap[$dp] = 0;
    }

    // Operations
    switch ($op) {
        case '!':
            $op = $code[++$ip];
            $count = $code[++$ip];

            switch($op) {
                case '+':
                    $heap[$dp] += $count;
                    break;
                case '-':
                    $heap[$dp] -= $count;
                    break;
                case '>':
                    $dp += $count;
                    break;
                case '<':
                    $dp -= $count;
                    break;
            }
            break;
        case '+':
            $heap[$dp] += 1;
            break;
        case '-':
            $heap[$dp] -= 1;
            break;
        case '>':
            $dp++;
            break;
        case '<':
            $dp--;
            break;
        case '.':
            echo chr($heap[$dp]);
            break;
        case '[':
            if ($heap[$dp] == 0) {
                $ip = $loopEnd[$ip];
            }
            break;
        case ']':
            if ($heap[$dp] !== 0) {
                $ip = $loopStart[$ip];
            }
            break;
        default:
            throw new InvalidArgumentException("Unknown operator: " . $op);
    }

    $ip++;
}

echo "Instruction count: \t $instructions";

