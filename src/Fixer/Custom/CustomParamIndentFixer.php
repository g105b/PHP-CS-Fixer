<?php
namespace PhpCsFixer\Fixer\Custom;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;

final class CustomParamIndentFixer extends AbstractFixer implements WhitespacesAwareFixerInterface
{
    /**
     * {@inheritdoc}
     */
    public function isCandidate(Tokens $tokens)
    {
        return $tokens->isTokenKindFound(T_FUNCTION);
    }

    private $paramLimit = 80;
    private $totalChar = 0;

    /**
     * {@inheritdoc}
     */
    public function fix(\SplFileInfo $file, Tokens $tokens)
    {
        for ($index = $tokens->count() - 1; $index >= 0; --$index) {
            $token = $tokens[$index];

            if (!$token->isGivenKind(T_FUNCTION)) {
                continue;
            }

            $startLineFunction = $index;

            for ($x = 1; $x <= 2; $x++) {
                $startLineFunction = $this->checkStartLine($startLineFunction, $tokens);
            }

            // $prevIndexNonWhitespaceFunction = $tokens->getPrevNonWhitespace($index);
            // $prevTokenNonWhitespaceFunction = $tokens[$prevIndexNonWhitespaceFunction];
            // if($prevTokenNonWhitespaceFunction->isGivenKind(array(T_PUBLIC,T_PROTECTED,T_PRIVATE,T_STATIC,T_CONST,T_VAR))){
            //     $startLineFunction = $prevIndexNonWhitespaceFunction;
            // }

            for ($i = $startLineFunction; $i <= count($tokens)-1;$i++) {
                if ($tokens[$i]->equals("{")) {
                    $endLineFunction = $i;
                    break;
                }
            }

            for ($i = $endLineFunction;$i >= $startLineFunction;$i--) {
                $this->totalChar += strlen($tokens[$i]->getContent());
            }

            $startParenthesisIndex = $tokens->getNextTokenOfKind($index, array('(', ';', array(T_CLOSE_TAG)));
            if (!$tokens[$startParenthesisIndex]->equals('(')) {
                continue;
            }

            $endParenthesisIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $startParenthesisIndex);
            $indentFunction = $this->detectIndent($tokens, $index);
            $variableIndexes = [];
            for ($iter = $endParenthesisIndex - 1; $iter > $startParenthesisIndex; --$iter) {
                if (!$tokens[$iter]->isGivenKind(T_VARIABLE)) {
                    continue;
                }
                $variableIndexes[] = $iter;
            }

            if ($this->totalChar > $this->paramLimit) {
                $tokens->ensureWhitespaceAtIndex($endParenthesisIndex-1, 1, $this->whitespacesConfig->getLineEnding().$indentFunction);
                foreach ($variableIndexes as $varIndex) {
                    $prevNonWhitespaceIndex = $tokens->getPrevNonWhitespace($varIndex);
                    if ($tokens[$prevNonWhitespaceIndex]->equalsAny(array('(', ','))) {
                        $tokens->ensureWhitespaceAtIndex($prevNonWhitespaceIndex + 1, 1, $this->whitespacesConfig->getLineEnding().$indentFunction."\t");
                    } elseif (!$tokens[$prevNonWhitespaceIndex]->equalsAny(array(array(T_COMMENT), array(T_DOC_COMMENT)))) {
                        $tokens->ensureWhitespaceAtIndex($prevNonWhitespaceIndex - 1, 1, $this->whitespacesConfig->getLineEnding().$indentFunction."\t");
                    }
                }
            }

            $this->totalChar = 0;
        }
    }

    private function checkStartLine($index, $tokens)
    {
        $prevIndexNonWhitespaceFunction = $tokens->getPrevNonWhitespace($index);
        $prevTokenNonWhitespaceFunction = $tokens[$prevIndexNonWhitespaceFunction];
        if ($prevTokenNonWhitespaceFunction->isGivenKind(array(T_PUBLIC,T_PROTECTED,T_PRIVATE,T_STATIC,T_CONST,T_VAR))) {
            $startLineFunction = $prevIndexNonWhitespaceFunction;
        } else {
            $startLineFunction = $index;
        }
        return $startLineFunction;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDescription()
    {
        return 'Add missing space between function\'s argument and its typehint.';
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
}
