<?php

interface qtype_pmatch_can_match_char {
    public function match_char($char);
}
interface qtype_pmatch_can_match_multiple_or_no_chars {
    public function match_chars($chars);
}
interface qtype_pmatch_can_match_word {
    public function match_word($word);
}
interface qtype_pmatch_can_match_phrase {
    /**
     * 
     * Can possibly match a phrase.
     * @param array $phrase array of words
     */
    public function match_phrase($phrase);
}

abstract class qtype_pmatch_matcher_item{
    protected $interpreter;
    /**
     * 
     * Constructor normally called by qtype_pmatch_interpreter_item->get_matcher method
     * @param qtype_pmatch_interpreter_item $interpreter
     */
    public function __construct($interpreter){
        $this->interpreter = $interpreter;
    }

    /**
     * 
     * Used for testing purposes. To make sure type and type of contents is as expected.
     */
    public function get_type(){
        $typeobj = new stdClass();
        $typeobj->name = $this->get_type_name($this);
        return $typeobj;
    }
    public function get_type_name($object){
        return substr(get_class($object), 21);
    }
}
abstract class qtype_pmatch_matcher_item_with_subcontents extends qtype_pmatch_matcher_item{

    protected $subcontents = array();
    
    /**
     * 
     * Create a tree of matcher items.
     * @param qtype_pmatch_interpreter_item_with_subcontents $interpreter
     */
    public function __construct($interpreter){
        parent::__construct($interpreter);
        $interpretersubcontents = $interpreter->get_subcontents();
        foreach ($interpretersubcontents as $interpretersubcontent){
            $this->subcontents[] = $interpretersubcontent->get_matcher();
        }
    }
    /**
     * 
     * Used for testing purposes. To make sure type and type of contents is as expected.
     */
    public function get_type(){
        $typeobj = new stdClass();
        $typeobj->name = $this->get_type_name($this);
        $typeobj->subcontents = array();
        foreach ($this->subcontents as $subcontent){
            $typeobj->subcontents[] = $subcontent->get_type();
        }
        return $typeobj;
    }
    
}

class qtype_pmatch_matcher_whole_expression extends qtype_pmatch_matcher_item_with_subcontents{

}
class qtype_pmatch_matcher_not extends qtype_pmatch_matcher_item_with_subcontents{
}
class qtype_pmatch_matcher_match extends qtype_pmatch_matcher_item_with_subcontents{
}
class qtype_pmatch_matcher_match_any extends qtype_pmatch_matcher_match{
}

class qtype_pmatch_matcher_match_all extends qtype_pmatch_matcher_match{
}

class qtype_pmatch_matcher_match_options extends qtype_pmatch_matcher_match{
}
class qtype_pmatch_matcher_or_list extends qtype_pmatch_matcher_item_with_subcontents
            implements qtype_pmatch_can_match_phrase, qtype_pmatch_can_match_word{
    public function match_word($word){
        foreach ($this->subcontents as $subcontent){
            if ($subcontent instanceof qtype_pmatch_can_match_word &&
                        $subcontent->match_word($word) === true){
                return true;
            }
        }
        return false;
    }
    public function match_phrase($phrase){
        foreach ($this->subcontents as $subcontent){
            if ($subcontent instanceof qtype_pmatch_can_match_phrase &&
                        $subcontent->match_phrase($phrase) === true){
                return true;
            }
        }
        return false;
    }

}
class qtype_pmatch_matcher_or_character extends qtype_pmatch_matcher_item{

}
class qtype_pmatch_matcher_or_list_phrase extends qtype_pmatch_matcher_item_with_subcontents
            implements qtype_pmatch_can_match_phrase{
    public function match_phrase($phrase){
        foreach ($this->subcontents as $subcontent){
            if ($subcontent instanceof qtype_pmatch_can_match_phrase &&
                        $subcontent->match_phrase($phrase) === true){
                return true;
            }
        }
        return false;
    }
}


class qtype_pmatch_matcher_phrase extends qtype_pmatch_matcher_item_with_subcontents
            implements qtype_pmatch_can_match_phrase{
    public function match_phrase($phrase){
        $wordno = 0;
        $subcontentno = 0;
        do {
            $subcontent = $this->subcontents[$subcontentno];
            $word = $phrase[$wordno];
            if ($subcontent instanceof qtype_pmatch_can_match_word){
                if ($subcontent->match_word($word) !== true){
                    return false;
                }
                $wordno++;
            } 
            $subcontentno++;
            $nomorewords = (count($phrase) < ($wordno + 1));
            $nomoreitems = (count($this->subcontents) < ($subcontentno + 1));
            if ($nomorewords && $nomoreitems){
                return true;
            } else if ($nomorewords || $nomoreitems) {
                return false;
            }
        } while (true);
    }
}
class qtype_pmatch_matcher_word_delimiter extends qtype_pmatch_matcher_item{
}
class qtype_pmatch_matcher_word extends qtype_pmatch_matcher_item_with_subcontents implements qtype_pmatch_can_match_word{
    public function match_word($word){
        return $this->check_match_branches($word);
    }
    private function check_match_branches($word, $charpos = 0, $subcontentno = 0, $noofcharactertomatch = 1){
        if ($this->subcontents[$subcontentno] instanceof qtype_pmatch_can_match_multiple_or_no_chars){
            $thisfragmentmatched = $this->subcontents[$subcontentno]->match_chars(substr($word, $charpos, $noofcharactertomatch));
        } else {
            $thisfragmentmatched = $this->subcontents[$subcontentno]->match_char(substr($word, $charpos, $noofcharactertomatch));
        }
        $itemslefttomatch = count($this->subcontents) - ($subcontentno + 1);
        $charslefttomatch = strlen($word) - ($charpos + $noofcharactertomatch);
        if (($noofcharactertomatch == 1) &&
                $this->subcontents[$subcontentno] instanceof qtype_pmatch_can_match_multiple_or_no_chars){
            if ($this->check_match_branches($word, $charpos, $subcontentno + 1, 1)){
                return true;
            }
        }
        if ($thisfragmentmatched){
            if ($charslefttomatch == 0){
                //reached end of word
                if ($itemslefttomatch == 0){
                    //all items match
                    return true;
                } else {
                    //this branch reached end of word prematurely
                    return false;
                }
            } else {
                if ($itemslefttomatch == 0){
                    return false;
                } else if ($this->check_match_branches($word, $charpos + $noofcharactertomatch, $subcontentno + 1, 1)){
                    return true;
                }
                if ($this->subcontents[$subcontentno] instanceof qtype_pmatch_can_match_multiple_or_no_chars){
                    if ($this->check_match_branches($word, $charpos, $subcontentno, $noofcharactertomatch + 1)){
                        return true;
                    }
                }
            }
        } else {
            return false;
        }
    }
}
class qtype_pmatch_matcher_character_in_word extends qtype_pmatch_matcher_item implements qtype_pmatch_can_match_char{
    public function match_char($character){
        $codefragment = $this->interpreter->get_code_fragment();
        return ($character == $codefragment);
    }
}
class qtype_pmatch_matcher_special_character_in_word extends qtype_pmatch_matcher_item implements qtype_pmatch_can_match_char{
    public function match_char($character){
        $codefragment = $this->interpreter->get_code_fragment();
        return ($character == $codefragment[1]);
    }
}
class qtype_pmatch_matcher_wildcard_match_single extends qtype_pmatch_matcher_item implements qtype_pmatch_can_match_char{
    public function match_char($character){
        return array(true);
    }
}
class qtype_pmatch_matcher_wildcard_match_multiple 
            extends qtype_pmatch_matcher_item implements qtype_pmatch_can_match_multiple_or_no_chars{

    public function match_chars($characters){
        return true;
    }

}