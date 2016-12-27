<?php
namespace PhpCsFixer\Fixer\Gt;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;

final class NoIndentNewLineParamPhpMethodFixer extends AbstractFixer implements WhitespacesAwareFixerInterface
{
    /**
     * {@inheritdoc}
     */
    public function isCandidate(Tokens $tokens)
    {
        return $tokens->isAnyTokenKindsFound($this->getPhpMethodTokenKinds());
    }

    /**
     * {@inheritdoc}
     */
    public function fix(\SplFileInfo $file, Tokens $tokens)
    {
        $phpMethodTokens = $this->getPhpMethodTokenKinds();
        foreach ($tokens as $index => $token) {
            // looking for start brace
            if (!$token->equals('(')) {
                continue;
            }

            // last non-whitespace token
            $lastTokenIndex = $tokens->getPrevNonWhitespace($index);

            if (null === $lastTokenIndex) {
                continue;
            }

            // check for ternary operator

            // check if it is a function call
            if ($tokens[$lastTokenIndex]->isGivenKind($phpMethodTokens)) {
                $endParenthesisIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $index);
                $nextNonWhiteSpace = $tokens->getNextMeaningfulToken($endParenthesisIndex);
                if (
                    null !== $nextNonWhiteSpace
                    && $tokens[$nextNonWhiteSpace]->equals('?')
                ) {
                    continue;
                }
                $indent = $this->detectIndent($tokens, $lastTokenIndex);
                for($i = $endParenthesisIndex-1; $i > $index; $i--){
                    $isNewLine = strpos($tokens[$i]->getContent(), "\n");
                    if($tokens[$i]->isWhitespace() && $isNewLine !== false){
                        $tokens->ensureWhitespaceAtIndex($i, 1, $this->whitespacesConfig->getLineEnding().$indent);
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getDescription()
    {
        return 'Inside a class there MUST NOT be any unnecessary double-indentation.';
    }

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

    /**
     * Gets the token kinds which can work as function calls.
     *
     * @return int[] Token names
     */
    private function getPhpMethodTokenKinds()
    {
        static $tokens = null;

        if (null === $tokens) {
            $tokens = array(
                T_IF,
                T_ELSEIF,
                T_SWITCH,
                T_FOR,
                T_FOREACH,
                T_WHILE
            );
        }

        return $tokens;
    }

    public function getPriority()
    {
        // should be run after the ElseIfFixer, NoEmptyStatementFixer and NoUselessElseFixer
        return -30;
    }

    /**
     * Gets the token kinds of actually language construction.
     *
     * @return int[]
     */
//    private function getLanguageConstructionTokenKinds()
//    {
//        static $languageConstructionTokens = array(
//            T_ECHO,
//            T_PRINT,
//            T_INCLUDE,
//            T_INCLUDE_ONCE,
//            T_REQUIRE,
//            T_REQUIRE_ONCE,
//        );
//
//        return $languageConstructionTokens;
//    }
}
