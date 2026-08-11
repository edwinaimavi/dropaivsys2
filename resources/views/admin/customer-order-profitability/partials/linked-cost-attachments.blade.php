<div class="cop-attachment-actions">
    @if($attachments->isEmpty())
        <span class="cop-no-attachments"><i class="far fa-folder-open"></i>Sin adjuntos</span>
    @else
        @foreach($attachments as $attachment)
            @if($attachment['status'] === 'available')
                <a href="{{$attachment['view_url']}}" target="_blank" rel="noopener" class="cop-attachment-action {{$attachment['is_image'] ? 'cop-preview-linked-image' : ''}}" data-label="{{$attachment['label']}}">
                    <i class="fas fa-eye"></i>{{$attachment['label']}}
                </a>
            @else
                <span class="cop-attachment-missing" title="El archivo adjunto no se encuentra disponible.">
                    <i class="fas fa-exclamation-triangle"></i>Archivo no encontrado
                </span>
            @endif
        @endforeach
    @endif
</div>
