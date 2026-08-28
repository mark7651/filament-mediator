<?php

namespace Mediator\Tests\Fixtures;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Mediator\Filament\Forms\MediaField;
use Mediator\Filament\Forms\MediaImagePlugin;

/**
 * A form of a project holding two files and a text, which is every place the
 * library is reached from outside its own section.
 */
class ArticleForm extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public Article $article;

    public function mount(?Article $article = null): void
    {
        $this->article = $article ?? Article::query()->create();

        $this->form->fill($this->article->only(['cover_id', 'icon_id', 'body']));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                MediaField::make('cover_id'),
                MediaField::make('icon_id')->takes(['image/svg+xml', 'image/png']),
                RichEditor::make('body')
                    ->plugins([MediaImagePlugin::make()])
                    // The tool comes with the plugin, standing it in the
                    // toolbar is the business of the form that wants it.
                    ->toolbarButtons([['bold', 'italic'], ['image']]),
            ])
            ->statePath('data')
            ->model($this->article);
    }

    public function save(): void
    {
        $this->article->update($this->form->getState());
    }

    public function render(): View
    {
        return view('article-form');
    }
}
