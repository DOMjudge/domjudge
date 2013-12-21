{-
 - This program tries to access the testcases during compilation
 - time and should fail with a COMPILER-ERROR.
 -
 - @EXPECTED_RESULTS@: COMPILER-ERROR
 -}

{-# LANGUAGE TemplateHaskell #-}

import Language.Haskell.TH
import System.IO.Unsafe
import System.Directory
import Data.List

answer = $(stringE $ unsafePerformIO $ getDirectoryContents "../../testcase" >>= mapM (\f -> readFile $ "../../testcase/" ++ f) . filter (\n -> "testcase.hello." `isPrefixOf` n && ".out" `isSuffixOf` n) >>= return . concat)

main = putStr answer
