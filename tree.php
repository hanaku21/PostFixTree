
<html>
	<head>
		<title></title>
	</head>
	<body>
	<?php
	function nfx($expr) {

        $index = 0;
        $stack = new EvalMathStack;
        $output = array(); // postfix form of expression, to be passed to pfx()
        $expr = trim(strtolower($expr));

        $ops   = array('+', '-', '*', '/', '^', '_');
        $ops_r = array('+'=>0,'-'=>0,'*'=>0,'/'=>0,'^'=>1); // right-associative operator?  
        $ops_p = array('+'=>0,'-'=>0,'*'=>1,'/'=>1,'_'=>1,'^'=>2); // operator precedence

        $expecting_op = false; // we use this in syntax-checking the expression
                               // and determining when a - is a negation

        if (preg_match("/[^\w\s+*^\/()\.,-]/", $expr, $matches)) { // make sure the characters are all good
            return $this->trigger("illegal character '{$matches[0]}'");
        }

        while(1) { // 1 Infinite Loop ;)
            $op = substr($expr, $index, 1); // get the first character at the current index
            // find out if we're currently at the beginning of a number/variable/function/parenthesis/operand
            $ex = preg_match('/^([a-z]\w*\(?|\d+(?:\.\d*)?|\.\d+|\()/', substr($expr, $index), $match);
            //===============
            if ($op == '-' and !$expecting_op) { // is it a negation instead of a minus?
                $stack->push('_'); // put a negation on the stack
                $index++;
            } elseif ($op == '_') { // we have to explicitly deny this, because it's legal on the stack 
                return $this->trigger("illegal character '_'"); // but not in the input expression
            //===============
            } elseif ((in_array($op, $ops) or $ex) and $expecting_op) { // are we putting an operator on the stack?
                if ($ex) { // are we expecting an operator but have a number/variable/function/opening parethesis?
                    $op = '*'; $index--; // it's an implicit multiplication
                }
                // heart of the algorithm:
                while($stack->count > 0 and ($o2 = $stack->last()) and in_array($o2, $ops) and ($ops_r[$op] ? $ops_p[$op] < $ops_p[$o2] : $ops_p[$op] <= $ops_p[$o2])) {
                    $output[] = $stack->pop(); // pop stuff off the stack into the output
                }
                // many thanks: http://en.wikipedia.org/wiki/Reverse_Polish_notation#The_algorithm_in_detail
                $stack->push($op); // finally put OUR operator onto the stack
                $index++;
                $expecting_op = false;
            //===============
            } elseif ($op == ')' and $expecting_op) { // ready to close a parenthesis?
                while (($o2 = $stack->pop()) != '(') { // pop off the stack back to the last (
                    if (is_null($o2)) return $this->trigger("unexpected ')'");
                    else $output[] = $o2;
                }
                if (preg_match("/^([a-z]\w*)\($/", $stack->last(2), $matches)) { // did we just close a function?
                    $fnn = $matches[1]; // get the function name
                    $arg_count = $stack->pop(); // see how many arguments there were (cleverly stored on the stack, thank you)
                    $output[] = $stack->pop(); // pop the function and push onto the output
                    if (in_array($fnn, $this->fb)) { // check the argument count
                        if($arg_count > 1)
                            return $this->trigger("too many arguments ($arg_count given, 1 expected)");
                    } elseif (array_key_exists($fnn, $this->f)) {
                        if ($arg_count != count($this->f[$fnn]['args']))
                            return $this->trigger("wrong number of arguments ($arg_count given, " . count($this->f[$fnn]['args']) . " expected)");
                    } else { // did we somehow push a non-function on the stack? this should never happen
                        return $this->trigger("internal error");
                    }
                }
                $index++;
            //===============
            } elseif ($op == ',' and $expecting_op) { // did we just finish a function argument?
                while (($o2 = $stack->pop()) != '(') { 
                    if (is_null($o2)) return $this->trigger("unexpected ','"); // oops, never had a (
                    else $output[] = $o2; // pop the argument expression stuff and push onto the output
                }
                // make sure there was a function
                if (!preg_match("/^([a-z]\w*)\($/", $stack->last(2), $matches))
                    return $this->trigger("unexpected ','");
                $stack->push($stack->pop()+1); // increment the argument count
                $stack->push('('); // put the ( back on, we'll need to pop back to it again
                $index++;
                $expecting_op = false;
            //===============
            } elseif ($op == '(' and !$expecting_op) {
                $stack->push('('); // that was easy
                $index++;
                $allow_neg = true;
            //===============
            } elseif ($ex and !$expecting_op) { // do we now have a function/variable/number?
                $expecting_op = true;
                $val = $match[1];
                if (preg_match("/^([a-z]\w*)\($/", $val, $matches)) { // may be func, or variable w/ implicit multiplication against parentheses...
                    if (in_array($matches[1], $this->fb) or array_key_exists($matches[1], $this->f)) { // it's a func
                        $stack->push($val);
                        $stack->push(1);
                        $stack->push('(');
                        $expecting_op = false;
                    } else { // it's a var w/ implicit multiplication
                        $val = $matches[1];
                        $output[] = $val;
                    }
                } else { // it's a plain old var or num
                    $output[] = $val;
                }
                $index += strlen($val);
            //===============
            } elseif ($op == ')') { // miscellaneous error checking
                return $this->trigger("unexpected ')'");
            } elseif (in_array($op, $ops) and !$expecting_op) {
                return $this->trigger("unexpected operator '$op'");
            } else { // I don't even want to know what you did to get here
                return $this->trigger("an unexpected error occured");
            }
            if ($index == strlen($expr)) {
                if (in_array($op, $ops)) { // did we end with an operator? bad.
                    return $this->trigger("operator '$op' lacks operand");
                } else {
                    break;
                }
            }
            while (substr($expr, $index, 1) == ' ') { // step the index past whitespace (pretty much turns whitespace 
                $index++;                             // into implicit multiplication if no operator is there)
            }

        } 
        while (!is_null($op = $stack->pop())) { // pop everything off the stack and push onto output
            if ($op == '(') return $this->trigger("expecting ')'"); // if there are (s on the stack, ()s were unbalanced
            $output[] = $op;
        }
        return $output;
    }
	?>
	</body>
</html>
