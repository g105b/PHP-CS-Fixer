<?php
namespace PhpCsFixer\Fixer\Gt;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\TokensAnalyzer;

final class SwitchCaseIndentationFixer extends AbstractFixer implements WhitespacesAwareFixerInterface
{
    /**
     * {@inheritdoc}
     */
    public function isCandidate(Tokens $tokens)
    {
       return $tokens->isAnyTokenKindsFound(array(T_SWITCH, T_CASE, T_DEFAULT));
    }

    /**
     * {@inheritdoc}
     */
    public function fix(\SplFileInfo $file, Tokens $tokens)
    {
        for ($index = 0, $limit = count($tokens); $index < $limit; ++$index) {
            $token = $tokens[$index];
            if(!$token->isGivenKind(array(T_SWITCH))){
                continue;
            }
            $tokensAnalyzer = new TokensAnalyzer($tokens);
            $switchOpenIndex = $tokens->getNextTokenOfKind($index, array('{'));
            $switchEndIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $switchOpenIndex);
            $indent = $this->detectIndent($tokens, $index);
            $tokens->ensureWhitespaceAtIndex($switchEndIndex - 1, 1, $this->whitespacesConfig->getLineEnding().$indent);
            for ($dIndex = $switchOpenIndex; $dIndex !== $switchEndIndex; $dIndex += 1) {
                $dtoken = $tokens[$dIndex];
                if ($dtoken->isGivenKind(array(T_SWITCH))) {
                    $dswitchOpenIndex = $tokens->getNextTokenOfKind($dIndex, array('{'));
                    $dswitchEndIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $dswitchOpenIndex);
                    $dIndex = $dswitchEndIndex;
                    continue;
                }
                if($dtoken->isGivenKind(array(T_CASE, T_DEFAULT))){
                     $dIndexPrevNonWhiteSpace = $tokens->getPrevNonWhitespace($dIndex);
                    $dTokenPrevNonWhiteSpace = $tokens[$dIndexPrevNonWhiteSpace];
                    $dIndexTwoPrevNonWhiteSpace = $tokens->getPrevNonWhitespace($dIndexPrevNonWhiteSpace);
                    $dTokenTwoPrevNonWhiteSpace = $tokens[$dIndexTwoPrevNonWhiteSpace];
                    if($dTokenPrevNonWhiteSpace->equals(';') and $dTokenTwoPrevNonWhiteSpace->isGivenKind(array(T_BREAK))){
                        $tokens->ensureWhitespaceAtIndex($dIndex - 1, 1, $this->whitespacesConfig->getLineEnding().$this->whitespacesConfig->getLineEnding().$indent);
                    } else {
                        $tokens->ensureWhitespaceAtIndex($dIndex - 1, 1, $this->whitespacesConfig->getLineEnding().$indent);
                    }
                     $startCaseDefaultIndex = $dIndex;
                     $endCaseDefaultIndex = $this->findCaseDefaultEnd($startCaseDefaultIndex,$switchEndIndex, $tokens);
                    $indentInside = $this->detectIndent($tokens, $dIndex);
                    // fix indent between braces
                    $lastCommaIndex = $tokens->getPrevTokenOfKind($endCaseDefaultIndex - 1, array(';', '}'));
                    $caseDefaultOpeningIndex = $tokens->getNextTokenOfKind($startCaseDefaultIndex, array(':'));
                    $nestLevel = 1;
                    for ($nestIndex = $lastCommaIndex; $nestIndex >= $caseDefaultOpeningIndex; --$nestIndex) {
                        $nestToken = $tokens[$nestIndex];

                        if ($nestToken->equals(')')) {
                            $nestIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $nestIndex, false);
                            continue;
                        }

                        if (1 === $nestLevel && $nestToken->equalsAny(array(';', '}', ':'))) {
                            $nextNonWhitespaceNestIndex = $tokens->getNextNonWhitespace($nestIndex);

                            $nextNonWhitespaceNestToken = $tokens[$nextNonWhitespaceNestIndex];
                            if (
                                // next Token is not a comment
                                !$nextNonWhitespaceNestToken->isComment() &&
                                // and it is not a `$foo = function () {};` situation
                                !($nestToken->equals('}') && $nextNonWhitespaceNestToken->equalsAny(array(';', ',', ']', array(CT::T_ARRAY_SQUARE_BRACE_CLOSE)))) &&
                                // and it is not a `Foo::{bar}()` situation
                                !($nestToken->equals('}') && $nextNonWhitespaceNestToken->equals('(')) &&
                                // and it is not a `${"a"}->...` and `${"b{$foo}"}->...` situation
                                !($nestToken->equals('}') && $tokens[$nestIndex - 1]->equalsAny(array('"', "'", array(T_CONSTANT_ENCAPSED_STRING))))
                            ) {
                                if (
                                    $nextNonWhitespaceNestToken->isGivenKind($this->getControlContinuationTokens())
                                    || $nextNonWhitespaceNestToken->isGivenKind(T_CLOSE_TAG)
                                    || (
                                        $nextNonWhitespaceNestToken->isGivenKind(T_WHILE) &&
                                        $tokensAnalyzer->isWhilePartOfDoWhile($nextNonWhitespaceNestIndex)
                                    )
                                ) {
                                    $whitespace = ' ';
                                } else {
                                    $nextToken = $tokens[$nestIndex + 1];
                                    $nextWhitespace = '';

                                    if ($nextToken->isWhitespace()) {
                                        $nextWhitespace = rtrim($nextToken->getContent(), " \t");

                                        if (strlen($nextWhitespace)) {
                                            $nextWhitespace = preg_replace(
                                                sprintf('/%s$/', $this->whitespacesConfig->getLineEnding()),
                                                '',
                                                $nextWhitespace,
                                                1
                                            );
                                        }
                                    }

                                    $whitespace = $nextWhitespace.$this->whitespacesConfig->getLineEnding().$indentInside;

                                    if (!$nextNonWhitespaceNestToken->equals('}')) {
                                        $whitespace .= "\t";
                                    }
                                }

                                $tokens->ensureWhitespaceAtIndex($nestIndex + 1, 0, $whitespace);

                            }
                        }
                    }
                }
            }
            $limit = count($tokens);
            // break;
         }
    }

    /**
     * Find block end.
     *
     * @param int  $type        type of block, one of BLOCK_TYPE_*
     * @param int  $searchIndex index of opening brace
     * @param bool $findEnd     if method should find block's end, default true, otherwise method find block's start
     *
     * @return int index of closing brace
     */
    public function findCaseDefaultEnd($searchIndex, $outerBlockEndIndex, $tokens)
    {

        $startIndex = $searchIndex + 1;
        $endIndex = $outerBlockEndIndex;
        $indexOffset = 1;

        if (!$tokens[$searchIndex]->isGivenKind(array(T_CASE, T_DEFAULT))) {
            throw new \InvalidArgumentException(sprintf('Invalid param $startIndex - not a proper block end.'));
        }

        for ($index = $startIndex; $index !== $endIndex; $index += $indexOffset) {
            $token = $tokens[$index];

            if ($token->isGivenKind(array(T_SWITCH))) {
                $switchOpenIndex = $tokens->getNextTokenOfKind($index, array('{'));
                $switchEndIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $switchOpenIndex);
                $index = $switchEndIndex;
                continue;
            }

            if ($token->isGivenKind(array(T_CASE, T_DEFAULT))) {
                break;
            }
        }

        if (!$tokens[$index]->isGivenKind(array(T_CASE, T_DEFAULT)) && $index != $endIndex) {
            throw new \UnexpectedValueException(sprintf('Missing block end.'));
        }

        return $index;
    }

    /**
     * @param Tokens $tokens
     * @param int    $index
     *
     * @return string
     */
    private function detectIndent(Tokens $tokens, $index)
    {
        while (true) {
            $whitespaceIndex = $tokens->getPrevTokenOfKind($index, array(array(T_WHITESPACE)));

            if (null === $whitespaceIndex) {
                return '';
            }

            $whitespaceToken = $tokens[$whitespaceIndex];

            if (false !== strpos($whitespaceToken->getContent(), "\n")) {
                break;
            }

            $prevToken = $tokens[$whitespaceIndex - 1];

            if ($prevToken->isGivenKind(array(T_OPEN_TAG, T_COMMENT)) && "\n" === substr($prevToken->getContent(), -1)) {
                break;
            }

            $index = $whitespaceIndex;
        }

        $explodedContent = explode("\n", $whitespaceToken->getContent());

        return end($explodedContent);
    }
    private function getControlContinuationTokens()
    {
        static $tokens = null;

        if (null === $tokens) {
            $tokens = array(
                T_ELSE,
                T_ELSEIF,
                T_CATCH,
            );

            if (defined('T_FINALLY')) {
                $tokens[] = T_FINALLY;
            }
        }

        return $tokens;
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        // should be run after the ElseIfFixer, NoEmptyStatementFixer and NoUselessElseFixer
        return -26;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDescription()
    {
        return 'The body of a switch statement MUST NOT receive double indentation.';
    }
}
