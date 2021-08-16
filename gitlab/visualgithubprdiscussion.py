import sys, requests, re
endpoint,github_pr = sys.argv[1:]

TYPE   = "application/vnd.github.v3+json"
URL_PR = "https://api.github.com/repos/domjudge/domjudge/pulls/{}".format(github_pr)
URL_IS = "https://api.github.com/repos/domjudge/domjudge/issues/{}/comments".format(github_pr)
BEGIN_RGX = "Changed URLs:"

def toMessage(url):
    global TYPE
    r = requests.get(url, headers={'Accept': TYPE})
    if isinstance(r.json(), list): 
        return [x['body'] for x in r.json()]
    else:
        return [r.json()['body']]

for message in toMessage(URL_PR)+toMessage(URL_IS):
    if message is None:
        continue
    index=message.find(BEGIN_RGX)
    if index>-1:
        for line in message[index:].splitlines()[1:]:
            if re.search(line,endpoint) is None:
                continue
            print("wanted")
            exit()
print("none")
