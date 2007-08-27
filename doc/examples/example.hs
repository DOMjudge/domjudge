import Prelude

main :: IO ()
main = do input <- getContents
          putStr.unlines.solveAll.tail.lines $ input

solveAll :: [String] -> [String]
solveAll = map (\x -> "Hello " ++ x ++ "!")
