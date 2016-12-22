<?php
namespace PhpCsFixer\Fixer\Custom;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use PhpCsFixer\Tokenizer\Tokens;

final class CustomIndentationTypeFixer extends AbstractFixer implements WhitespacesAwareFixerInterface
{
    /**
     * {@inheritdoc}
     */
    public function isCandidate(Tokens $tokens)
    {
        return $tokens->isAnyTokenKindsFound(array(T_COMMENT, T_DOC_COMMENT, T_WHITESPACE));
    }

    /**
     * {@inheritdoc}
     */
    public function fix(\SplFileInfo $file, Tokens $tokens)
    {
        foreach ($tokens as $index => $token) {
            if ($token->isComment()) {
                $content = preg_replace('/^(?:(?<! ) {1,3})?\t/m', '\1    ', $token->getContent(), -1, $count);

                // Also check for more tabs.
                while ($count !== 0) {
                    $content = preg_replace('/^(\ +)?\t/m', '\1    ', $content, -1, $count);
                }

                // change indent to expected one
                $content = preg_replace('/^    /m', '      ', $content);

                $tokens[$index]->setContent($content);
                continue;
            }

            if ($token->isWhitespace()) {
                // normalize mixed indent
                $content = preg_replace('/(?:(?<! ) {1,3})?\t/', '      ', $token->getContent());

                // change indent to expected one
                $content = str_replace('    ', '      ', $content);

                $tokens[$index]->setContent($content);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        return -100;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDescription()
    {
        return 'Code MUST use tabs for indentation, and MUST NOT use spaces for indenting.';
    }
}
