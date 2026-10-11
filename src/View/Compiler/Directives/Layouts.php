<?php

declare(strict_types=1);

namespace Slendie\View\Compiler\Directives;

/**
 * Directivas de layout: extends, section, yield, includes e stacks.
 */
trait Layouts
{
    protected function compileExtends(string $expression): string
    {
        $this->footer[] = "<?php echo \$__env->renderInclude(get_defined_vars(), {$expression}); ?>";

        return '';
    }

    protected function compileSection(string $expression): string
    {
        return "<?php \$__env->startSection({$expression}); ?>";
    }

    protected function compileEndsection(string $expression): string
    {
        return '<?php $__env->stopSection(); ?>';
    }

    protected function compileStop(string $expression): string
    {
        return '<?php $__env->stopSection(); ?>';
    }

    protected function compileShow(string $expression): string
    {
        return '<?php echo $__env->yieldSection(); ?>';
    }

    protected function compileOverwrite(string $expression): string
    {
        return '<?php $__env->stopSection(true); ?>';
    }

    protected function compileAppend(string $expression): string
    {
        return '<?php $__env->appendSection(); ?>';
    }

    protected function compileYield(string $expression): string
    {
        return "<?php echo \$__env->yieldContent({$expression}); ?>";
    }

    protected function compileParent(string $expression): string
    {
        return '<?php echo $__env->yieldParent(); ?>';
    }

    protected function compileHasSection(string $expression): string
    {
        return "<?php if (\$__env->hasSection({$expression})): ?>";
    }

    protected function compileSectionMissing(string $expression): string
    {
        return "<?php if (\$__env->sectionMissing({$expression})): ?>";
    }

    protected function compileInclude(string $expression): string
    {
        return "<?php echo \$__env->renderInclude(get_defined_vars(), {$expression}); ?>";
    }

    protected function compileIncludeIf(string $expression): string
    {
        return "<?php echo \$__env->renderIncludeIf(get_defined_vars(), {$expression}); ?>";
    }

    protected function compileIncludeWhen(string $expression): string
    {
        return "<?php echo \$__env->renderIncludeWhen(get_defined_vars(), {$expression}); ?>";
    }

    protected function compileIncludeUnless(string $expression): string
    {
        return "<?php echo \$__env->renderIncludeUnless(get_defined_vars(), {$expression}); ?>";
    }

    protected function compileIncludeFirst(string $expression): string
    {
        return "<?php echo \$__env->renderIncludeFirst(get_defined_vars(), {$expression}); ?>";
    }

    protected function compileStack(string $expression): string
    {
        return "<?php echo \$__env->yieldPushContent({$expression}); ?>";
    }

    protected function compilePush(string $expression): string
    {
        return "<?php \$__env->startPush({$expression}); ?>";
    }

    protected function compileEndpush(string $expression): string
    {
        return '<?php $__env->stopPush(); ?>';
    }

    protected function compilePrepend(string $expression): string
    {
        return "<?php \$__env->startPrepend({$expression}); ?>";
    }

    protected function compileEndprepend(string $expression): string
    {
        return '<?php $__env->stopPrepend(); ?>';
    }

    protected function compileOnce(string $expression): string
    {
        $id = sha1(($this->currentPath ?? 'template').':'.++$this->onceCounter);

        return "<?php if (\$__env->renderOnce('{$id}')): ?>";
    }

    protected function compileEndonce(string $expression): string
    {
        return '<?php endif; ?>';
    }
}
