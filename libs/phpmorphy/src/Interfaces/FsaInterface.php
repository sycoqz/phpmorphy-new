<?php

namespace Interfaces;

interface FsaInterface
{
    /**
     * Return root transition of fsa
     * @return array|int
     */
    public function getRootTrans(): array|int;

    /**
     * Returns root state object
     * @return
     */
    public function getRootState();

    /**
     * Returns alphabet i.e. all chars used in automat
     * @return array
     */
    public function getAlphabet(): array;

    /**
     * Return annotation for given transition(if annotation flag is set for given trans)
     *
     * @param array $trans
     * @return string
     */
    public function getAnnot(array $trans): string;

    /**
     * Find word in automat
     *
     * @param mixed $trans starting transition
     * @param string $word
     * @param bool $readAnnot read annot or simple check if word exists in automat
     * @return bool|array TRUE if word is found, FALSE otherwise
     */
    public function walk(mixed $trans, string $word, bool $readAnnot = true): bool|array;

    /**
     * Traverse automat and collect words
     * For each found words $callback function invoked with follow arguments:
     * call_user_func($callback, $word, $annot)
     * when $readAnnot is FALSE then $annot arg is always NULL
     *
     * @param mixed $startNode
     * @param mixed $callback callback function(in php format callback i.e. string or array(obj, method) or array(class, method)
     * @param bool $readAnnot read annot
     * @param string $path string to be append to all words
     */
    public function collect(mixed $startNode, mixed $callback, bool $readAnnot = true, string $path = '');

    /**
     * Read state at given index
     *
     * @param int $index
     * @return array
     */
    public function readState(int $index): array;

    /**
     * Unpack transition from binary form to array
     *
     * @param array $rawTranses may be array for convert more than one transitions
     * @return array
     */
    public function unpackTranses(array $rawTranses): array;
}
