# AWS IAM Keys to Github Secrets

Simple application to rotate AWS Keys into Github Secrets

## Running

The command takes a single argument, a path to a YML file. If this is not provided, then an example is given.

```bash
./aws-keys-rotate-into-github-secrets -c ~/.aws-keys-rotate-into-github.yml
```

The command will bail if the AWS User has more than one key/secret pair - if that happens, something has probably gone
wrong.

## Configuration

Create a YML file like the one below, but change the relevant key/secret values. A Github token must be generated that
has access to the relevant repositories.

```yaml
github:
  token: ghp_sausages
keys:
  identifier:
    aws:
      user: automaton
      maxKeyAge: P7D
      config:
        region: moon-north-1
        version: latest
        credentials:
          key: KEY
          secret: SECRET
    keyDestinations:
      - owner: sdrycroft
        repo: aws-keys-rotate-into-github-secrets
        key: AWS_KEY
      - owner: sdrycroft
        repo: another-repo
        key: AWS_KEY
    secretDestinations:
      - owner: sdrycroft
        repo: aws-keys-rotate-into-github-secrets
        key: AWS_SECRET
      - owner: sdrycroft
        repo: another-repo
        key: AWS_SECRET
  identifier2:
    aws:
      user: barry
      maxKeyAge: P7D
      config:
        region: moon-north-1
        version: latest
        credentials:
          key: KEY
          secret: SECRET
    keyDestinations:
      - owner: sdrycroft
        repo: aws-keys-rotate-into-github-secrets
        key: BARRY_AWS_KEY
      - owner: sdrycroft
        repo: another-repo
        key: BARRY_AWS_KEY
    secretDestinations:
      - owner: sdrycroft
        repo: aws-keys-rotate-into-github-secrets
        key: BARRY_AWS_SECRET
      - owner: sdrycroft
        repo: another-repo
        key: BARRY_AWS_SECRET
```
