/**
 * See: https://codegolf.stackexchange.com/a/24527
 * 
 * Make sure we also timeout when we only do garbage collection
 **/

public class TestGarbage {
  public TestGarbage() {}
  protected void finalize() {
    new TestGarbage();
    new TestGarbage();
  }

  public static void main(String[] args) throws InterruptedException {
    new TestGarbage();
    while (true) {
      // Prevent leaks ;-)
      System.gc();
      System.runFinalization();
    } 
  }
}