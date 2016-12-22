<?php
namespace PhpCsFixer\Fixer\Custom;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Tokenizer\Tokens;

final class ChangeElseifToElseIfFixer extends AbstractFixer
{
    /**
     * {@inheritdoc}
     */
    public function isCandidate(Tokens $tokens)
    {
        return $tokens->isTokenKindFound(T_ELSEIF);
    }

    /**
     * Replace all `elseif` (T_ELSEIF) with `else if` (T_ELSE T_IF).
     *
     * {@inheritdoc}
     */
    public function fix(\SplFileInfo $file, Tokens $tokens)
    {
        foreach ($tokens as $index => $token) {
            if ($token->isGivenKind(T_ELSEIF)) {
                $tokens->overrideAt($index, array(T_ELSE, 'else'));
                $tokens->ensureWhitespaceAtIndex($index + 1, 0, " if");
            } else {
                continue;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getDescription()
    {
        return 'The keyword else if should be used instead of elseif.';
    }

    public function getPriority()
    {
        return -29;
    }
}
