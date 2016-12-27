<?php
namespace PhpCsFixer\Fixer\Gt;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;

final class NewlineBeforeElseElseifFixer extends AbstractFixer implements WhitespacesAwareFixerInterface
{
    /**
     * {@inheritdoc}
     */
    public function isCandidate(Tokens $tokens)
    {
        return $tokens->isAllTokenKindsFound(array(T_ELSEIF, T_ELSE));
    }

    /**
     * {@inheritdoc}
     */
    public function fix(\SplFileInfo $file, Tokens $tokens)
    {
        foreach ($tokens as $index => $token) {
            if (!$token->isGivenKind(array(T_ELSE, T_ELSEIF))) {
                continue;
            }

            $ifTokenIndex = $tokens->getPrevTokenOfKind($index, array(array(T_IF)));;
            $indent = $this->detectIndent($tokens, $ifTokenIndex);

            // if next meaning token is not T_IF - continue searching, this is not the case for fixing
            if (!$tokens[$ifTokenIndex]->isGivenKind(T_IF)) {
                continue;
            }

            $tokens->ensureWhitespaceAtIndex($index - 1, 1, $this->whitespacesConfig->getLineEnding().$indent);
        }
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
     * {@inheritdoc}
     */
    protected function getDescription()
    {
        return 'The keyword elseif MUST start on its own line.';
    }

    public function getPriority()
    {
        return -30;
    }
}
