# Github-Wiki-to-Gitbook
Automaticaly synchronize a Gitbook website from a Github Wiki repository

> This PHP script will generate a Gitbook automaticaly from a Github Wiki repository
> Executing this script with a cron, your Gitbook will always be updated if a modification is done on the Github Wiki.

## Important instructions when editing your Github Wiki

When Gitbook generates the wiki pages, it first get the list of the pages from the sidebar menu (on the right on Github, called SUMMARY on Github).

So you must specify all your wiki pages in this sidebar using \[\[internal links\]\] exacly like that :
```
### [[Title 1]]
* [[Page 1]]
* [[Page 2]]

### [[Title 2]]
* [[Page 3]]
* [[Page 4]]
```

The name of your wiki pages mustn't contain special characters like "?" ou "(".

[Gitbook reference manual](https://toolchain.gitbook.com)

## Installation

### Log to your server with you www-data account and execute theses commands adapting NAME and REPOSITORY
```
cd /var/www/your-website
git clone https://github.com/NAME/REPOSITORY.wiki.git
git clone https://github.com/marc-fun/Github-Wiki-to-Gitbook.git
npm install gitbook-cli
gitbook init
```

### Create the file /var/www/your-website/book.json containing :
```
{ 
  "gitbook": "2.x.x",
  "language": "en",
  "title": "Wiki Title",
  "author": "Your name",
  "structure": { 
  	"readme": "README.md",
    "summary": "SUMMARY.md"
  } 
}
```

### Configure Apache

```
DocumentRoot /var/www/your-website/_book
```

### Configure cron

Log with your www-data account :
```
crontab -e
```
And add :

`15 3  * *  * php /var/www/wiki.communecter.org/Github-Wiki-to-Gitbook/generate-gitbook-from-github.php`
