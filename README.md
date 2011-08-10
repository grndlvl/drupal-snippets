## Description
Creates snipMate snippets for Drupal to be used with Vim.

## Requirements
[snipMate](https://github.com/msanders/snipmate.vim) for Vim.

## Usage
Running the following command will create a directory inside of the current directory called "snippets/drupal" based on the code avaliable by the path passed in for Drupal.
For instance, if you would like devel functions form the devel module in your Drupal snippets then download the devel module and place it in the Drupal modules directory
and then run the generation script and it will create snippets for that module as well.

nce you get that up and running, just put the 'drupal' directory in your ~/.vim/snippets (or wherever you install your Vim scripts to).
Run the following:

`php generate.php [path to Drupal]`

If you installed this as a [pathogen](https://github.com/tpope/vim-pathogen) bundle then your done :).
Otherwise, move the "drupal" folder into [Vim scripts directory]/snippets(usually ~/.vim/snippets)

## Credits
Original code from [Steven Wittens'](http://acko.net/) original TextMate bundle script over [here](http://acko.net/blog/updated-drupal-textmate-bundle).
