{-
 - This should give CORRECT on the default problem 'hello',
 - since the random extra file will not be passed.
 -
 - @EXPECTED_RESULTS@: CORRECT
 -}

import System.IO
main = do	putStr "Hello world!\n"
