package main

import (
	"fmt"
)

func main() {
	var n int
	fmt.Scanf("%d", &n)

	for range n { // since go1.22 onwards
		var name string
		fmt.Scanf("%s", &name)
		fmt.Printf("Hello %s!\n", name)
	}
}
