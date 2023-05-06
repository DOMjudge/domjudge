'''
@EXPECTED_RESULTS@: RUN-ERROR
'''
storage = []

while True:
    some_str = ' ' * bytearray(512000000)
    storage.append(some_str)
