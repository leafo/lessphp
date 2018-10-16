<?php

/**
 * create a less file from a css file by combining blocks where appropriate
 */
class lessify extends lessc {

    public function dump() {
        print_r($this->env);
    }

    public function parse($str = null) {
        $this->prepareParser($str ? $str : $this->buffer);
        while (false !== $this->parseChunk());

        $root = new nodecounter(null);

        // attempt to preserve some of the block order
        $order = array();

        $visitedTags = array();
        foreach (end($this->env) as $name => $block) {
            if (!$this->isBlock($name, $block)) continue;
            if (isset($visitedTags[$name])) continue;

            foreach ($block['__tags'] as $t) {
                $visitedTags[$t] = true;
            }

            // skip those with more than 1
            if (count($block['__tags']) == 1) {
                $p = new tagparse(end($block['__tags']));
                $path = $p->parse();
                $root->addBlock($path, $block);
                $order[] = array('compressed', $path, $block);
                continue;
            } else {
                $common = null;
                $paths = array();
                foreach ($block['__tags'] as $rawtag) {
                    $p = new tagparse($rawtag);
                    $paths[] = $path = $p->parse();
                    if (is_null($common)) $common = $path;
                    else {
                        $new_common = array();
                        foreach ($path as $tag) {
                            $head = array_shift($common);
                            if ($tag == $head) {
                                $new_common[] = $head;
                            } else break;
                        }
                        $common = $new_common;
                        if (empty($common)) {
                            // nothing in common
                            break;
                        }
                    }
                }

                if (!empty($common)) {
                    $new_paths = array();
                    foreach ($paths as $p) $new_paths[] = array_slice($p, count($common));
                    $block['__tags'] = $new_paths;
                    $root->addToNode($common, $block);
                    $order[] = array('compressed', $common, $block);
                    continue;
                }

            }

            $order[] = array('none', $block['__tags'], $block);
        }


        $compressed = $root->children;
        foreach ($order as $item) {
            list($type, $tags, $block) = $item;
            if ($type == 'compressed') {
                $top = tagparse::compileTag(reset($tags));
                if (isset($compressed[$top])) {
                    $compressed[$top]->compile($this);
                    unset($compressed[$top]);
                }
            } else {
                echo $this->indent(implode(', ', $tags).' {');
                $this->indentLevel++;
                nodecounter::compileProperties($this, $block);
                $this->indentLevel--;
                echo $this->indent('}');
            }
        }
    }
}
