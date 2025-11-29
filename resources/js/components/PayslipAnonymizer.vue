<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { 
    Undo2, 
    Trash2, 
    Check,
    Minus,
    Plus,
    Paintbrush,
    Loader2,
    ZoomIn,
    ZoomOut,
    RotateCcw
} from 'lucide-vue-next';
import * as pdfjsLib from 'pdfjs-dist';
import pdfjsWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

// Sæt worker path
pdfjsLib.GlobalWorkerOptions.workerSrc = pdfjsWorker;

interface Props {
    file: File;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'save', file: File): void;
    (e: 'cancel'): void;
}>();

// Canvas refs
const canvasContainerRef = ref<HTMLDivElement | null>(null);
const canvasRef = ref<HTMLCanvasElement | null>(null);
const ctx = ref<CanvasRenderingContext2D | null>(null);

// Image state
const originalImage = ref<HTMLImageElement | null>(null);
const imageLoaded = ref(false);
const isLoadingPdf = ref(false);
const loadError = ref<string | null>(null);

// Drawing state
const isDrawing = ref(false);
const brushSize = ref(10);
const brushColor = '#000000'; // Fast sort farve

// History for undo
const history = ref<ImageData[]>([]);
const historyIndex = ref(-1);
const maxHistory = 50;

// Canvas dimensions
const canvasWidth = ref(0);
const canvasHeight = ref(0);
const baseScale = ref(1); // Scale til at passe i container
const zoomLevel = ref(1); // Brugerens zoom level
const scale = computed(() => baseScale.value * zoomLevel.value);

// Zoom options
const minZoom = 0.5;
const maxZoom = 3;
const zoomStep = 0.25;

// Detect mobile device
const isMobile = ref(false);
const checkMobile = () => {
    isMobile.value = window.innerWidth < 640; // sm breakpoint
};

// Brush size options
const minBrushSize = 5;
const maxBrushSize = 80;
const brushStep = 5;

const isPdf = (file: File) => file.type === 'application/pdf';

const loadFile = async () => {
    loadError.value = null;
    
    if (isPdf(props.file)) {
        await loadPdf();
    } else {
        loadImage();
    }
};

const loadPdf = async () => {
    isLoadingPdf.value = true;
    
    try {
        const arrayBuffer = await props.file.arrayBuffer();
        const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
        
        // Hent første side
        const page = await pdf.getPage(1);
        
        // Render med høj kvalitet (scale 2 for bedre opløsning)
        const pdfScale = 2;
        const viewport = page.getViewport({ scale: pdfScale });
        
        // Opret midlertidigt canvas til PDF rendering
        const pdfCanvas = document.createElement('canvas');
        pdfCanvas.width = viewport.width;
        pdfCanvas.height = viewport.height;
        const pdfCtx = pdfCanvas.getContext('2d');
        
        if (!pdfCtx) {
            throw new Error('Kunne ikke oprette canvas context');
        }
        
        await page.render({
            canvasContext: pdfCtx,
            viewport: viewport
        }).promise;
        
        // Konverter canvas til billede
        const img = new Image();
        img.onload = () => {
            originalImage.value = img;
            imageLoaded.value = true;
            isLoadingPdf.value = false;
            nextTick(() => {
                setupCanvas();
            });
        };
        img.onerror = () => {
            loadError.value = 'Kunne ikke konvertere PDF til billede';
            isLoadingPdf.value = false;
        };
        img.src = pdfCanvas.toDataURL('image/png');
        
    } catch (error) {
        console.error('Fejl ved indlæsning af PDF:', error);
        loadError.value = 'Kunne ikke indlæse PDF-filen';
        isLoadingPdf.value = false;
    }
};

const loadImage = () => {
    const img = new Image();
    img.onload = () => {
        originalImage.value = img;
        imageLoaded.value = true;
        nextTick(() => {
            setupCanvas();
        });
    };
    img.onerror = () => {
        loadError.value = 'Kunne ikke indlæse billedet';
    };
    img.src = URL.createObjectURL(props.file);
};

const setupCanvas = () => {
    if (!canvasRef.value || !canvasContainerRef.value || !originalImage.value) return;

    const container = canvasContainerRef.value;
    const img = originalImage.value;

    // Calculate base scale to fit container while maintaining aspect ratio
    const containerWidth = container.clientWidth;
    const maxHeight = window.innerHeight * 0.5; // Max 50% of viewport height

    // Calculate base scale based on container width
    baseScale.value = containerWidth / img.width;
    
    // Check if height would exceed max
    if (img.height * baseScale.value > maxHeight) {
        baseScale.value = maxHeight / img.height;
    }

    // Reset zoom when loading new image
    zoomLevel.value = 1;

    updateCanvasSize();

    const canvas = canvasRef.value;
    canvas.width = img.width;
    canvas.height = img.height;

    ctx.value = canvas.getContext('2d', { willReadFrequently: true });
    
    if (ctx.value) {
        // Draw original image
        ctx.value.drawImage(img, 0, 0);
        
        // Save initial state
        saveToHistory();
    }
};

const updateCanvasSize = () => {
    if (!canvasRef.value || !originalImage.value) return;
    
    const img = originalImage.value;
    canvasWidth.value = img.width * scale.value;
    canvasHeight.value = img.height * scale.value;
    
    canvasRef.value.style.width = `${canvasWidth.value}px`;
    canvasRef.value.style.height = `${canvasHeight.value}px`;
};

const zoomIn = () => {
    if (zoomLevel.value < maxZoom) {
        zoomLevel.value = Math.min(zoomLevel.value + zoomStep, maxZoom);
        updateCanvasSize();
    }
};

const zoomOut = () => {
    if (zoomLevel.value > minZoom) {
        zoomLevel.value = Math.max(zoomLevel.value - zoomStep, minZoom);
        updateCanvasSize();
    }
};

const resetZoom = () => {
    zoomLevel.value = 1;
    updateCanvasSize();
};

const zoomPercentage = computed(() => Math.round(zoomLevel.value * 100));

const saveToHistory = () => {
    if (!ctx.value || !canvasRef.value) return;

    // Remove any redo states
    if (historyIndex.value < history.value.length - 1) {
        history.value = history.value.slice(0, historyIndex.value + 1);
    }

    // Add current state
    const imageData = ctx.value.getImageData(0, 0, canvasRef.value.width, canvasRef.value.height);
    history.value.push(imageData);
    historyIndex.value = history.value.length - 1;

    // Limit history size
    if (history.value.length > maxHistory) {
        history.value.shift();
        historyIndex.value--;
    }
};

const undo = () => {
    if (historyIndex.value > 0 && ctx.value) {
        historyIndex.value--;
        ctx.value.putImageData(history.value[historyIndex.value], 0, 0);
    }
};

const clearCanvas = () => {
    if (!ctx.value || !originalImage.value || !canvasRef.value) return;
    
    ctx.value.drawImage(originalImage.value, 0, 0);
    saveToHistory();
};

const getPointerPosition = (e: PointerEvent): { x: number; y: number } => {
    if (!canvasRef.value) return { x: 0, y: 0 };

    const rect = canvasRef.value.getBoundingClientRect();
    const x = (e.clientX - rect.left) / scale.value;
    const y = (e.clientY - rect.top) / scale.value;

    return { x, y };
};

const startDrawing = (e: PointerEvent) => {
    if (!ctx.value) return;
    
    isDrawing.value = true;
    
    const { x, y } = getPointerPosition(e);
    
    ctx.value.beginPath();
    ctx.value.moveTo(x, y);
    ctx.value.lineCap = 'round';
    ctx.value.lineJoin = 'round';
    ctx.value.strokeStyle = brushColor;
    ctx.value.lineWidth = brushSize.value / scale.value;
    
    // Draw a dot for single clicks
    ctx.value.lineTo(x, y);
    ctx.value.stroke();
};

const draw = (e: PointerEvent) => {
    if (!isDrawing.value || !ctx.value) return;

    const { x, y } = getPointerPosition(e);

    ctx.value.lineTo(x, y);
    ctx.value.stroke();
};

const stopDrawing = () => {
    if (isDrawing.value && ctx.value) {
        ctx.value.closePath();
        saveToHistory();
    }
    isDrawing.value = false;
};

const increaseBrushSize = () => {
    if (brushSize.value < maxBrushSize) {
        brushSize.value = Math.min(brushSize.value + brushStep, maxBrushSize);
    }
};

const decreaseBrushSize = () => {
    if (brushSize.value > minBrushSize) {
        brushSize.value = Math.max(brushSize.value - brushStep, minBrushSize);
    }
};

const saveImage = async () => {
    if (!canvasRef.value) return;

    canvasRef.value.toBlob((blob) => {
        if (blob) {
            // Create a new file with the same name but as PNG
            const originalName = props.file.name;
            const nameWithoutExt = originalName.substring(0, originalName.lastIndexOf('.')) || originalName;
            const newFile = new File([blob], `${nameWithoutExt}_anonymized.png`, {
                type: 'image/png',
            });
            emit('save', newFile);
        }
    }, 'image/png', 1.0);
};

const handleResize = () => {
    checkMobile();
    
    // Reset zoom på mobil
    if (isMobile.value && zoomLevel.value !== 1) {
        zoomLevel.value = 1;
    }
    
    if (imageLoaded.value && originalImage.value && ctx.value && canvasRef.value) {
        // Save current state
        const currentState = ctx.value.getImageData(0, 0, canvasRef.value.width, canvasRef.value.height);
        
        // Recalculate dimensions
        const container = canvasContainerRef.value;
        if (!container) return;

        const img = originalImage.value;
        const containerWidth = container.clientWidth;
        const maxHeight = window.innerHeight * 0.5;

        baseScale.value = containerWidth / img.width;
        if (img.height * baseScale.value > maxHeight) {
            baseScale.value = maxHeight / img.height;
        }

        updateCanvasSize();

        // Restore state
        ctx.value.putImageData(currentState, 0, 0);
    }
};

// Prevent scrolling while drawing on touch devices
const preventTouchScroll = (e: TouchEvent) => {
    if (isDrawing.value) {
        e.preventDefault();
    }
};

onMounted(() => {
    checkMobile();
    loadFile();
    window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
    // Kun ryd object URL for billeder (ikke PDF'er konverteret via canvas)
    if (originalImage.value && !isPdf(props.file)) {
        URL.revokeObjectURL(originalImage.value.src);
    }
});

watch(() => props.file, () => {
    history.value = [];
    historyIndex.value = -1;
    imageLoaded.value = false;
    isLoadingPdf.value = false;
    loadError.value = null;
    loadFile();
});

const canUndo = () => historyIndex.value > 0;
</script>

<template>
    <div class="space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold flex items-center gap-2">
                    <Paintbrush class="h-5 w-5" />
                    Anonymiser lønseddel
                </h3>
                <p class="text-sm text-muted-foreground">
                    Tegn over personlige oplysninger for at skjule dem
                </p>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-3 p-3 bg-muted/50 rounded-lg">
            <!-- Brush Size -->
            <div class="flex items-center gap-2">
                <Label class="text-xs text-muted-foreground whitespace-nowrap">Pensel:</Label>
                <div class="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="h-8 w-8"
                        @click="decreaseBrushSize"
                        :disabled="brushSize <= minBrushSize"
                    >
                        <Minus class="h-3 w-3" />
                    </Button>
                    <div class="w-8 text-center text-sm font-medium">
                        {{ brushSize }}
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="h-8 w-8"
                        @click="increaseBrushSize"
                        :disabled="brushSize >= maxBrushSize"
                    >
                        <Plus class="h-3 w-3" />
                    </Button>
                </div>
            </div>

            <!-- Zoom Controls (kun desktop) -->
            <div v-if="!isMobile" class="hidden sm:flex items-center gap-2">
                <Label class="text-xs text-muted-foreground whitespace-nowrap">Zoom:</Label>
                <div class="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="h-8 w-8"
                        @click="zoomOut"
                        :disabled="zoomLevel <= minZoom"
                        title="Zoom ud"
                    >
                        <ZoomOut class="h-3 w-3" />
                    </Button>
                    <button
                        type="button"
                        class="w-12 text-center text-sm font-medium hover:text-primary transition-colors"
                        @click="resetZoom"
                        title="Nulstil zoom"
                    >
                        {{ zoomPercentage }}%
                    </button>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="h-8 w-8"
                        @click="zoomIn"
                        :disabled="zoomLevel >= maxZoom"
                        title="Zoom ind"
                    >
                        <ZoomIn class="h-3 w-3" />
                    </Button>
                </div>
            </div>

            <!-- Spacer -->
            <div class="flex-1"></div>

            <!-- Actions -->
            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="undo"
                    :disabled="!canUndo()"
                    title="Fortryd"
                >
                    <Undo2 class="h-4 w-4 mr-1" />
                    <span class="hidden sm:inline">Fortryd</span>
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="clearCanvas"
                    title="Ryd alt"
                >
                    <Trash2 class="h-4 w-4 mr-1" />
                    <span class="hidden sm:inline">Ryd</span>
                </Button>
            </div>
        </div>

        <!-- Canvas Container -->
        <div 
            ref="canvasContainerRef"
            class="relative w-full rounded-lg border bg-muted/30 overflow-auto"
            :style="{ maxHeight: '50vh' }"
        >
            <!-- Loading state -->
            <div v-if="isLoadingPdf" class="flex flex-col items-center justify-center h-64 gap-3">
                <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
                <div class="text-muted-foreground">Konverterer PDF til billede...</div>
            </div>
            
            <!-- Error state -->
            <div v-else-if="loadError" class="flex flex-col items-center justify-center h-64 gap-3 text-destructive">
                <div>{{ loadError }}</div>
                <Button variant="outline" size="sm" @click="loadFile">
                    Prøv igen
                </Button>
            </div>
            
            <!-- Loading image -->
            <div v-else-if="!imageLoaded" class="flex items-center justify-center h-64">
                <div class="text-muted-foreground">Indlæser...</div>
            </div>
            
            <!-- Canvas wrapper for centering -->
            <div 
                v-show="imageLoaded && !loadError"
                class="flex justify-center items-start"
                :style="{ minWidth: canvasWidth > 0 ? `${canvasWidth}px` : 'auto' }"
            >
                <canvas
                    ref="canvasRef"
                    class="touch-none cursor-crosshair block flex-shrink-0"
                    @pointerdown="startDrawing"
                    @pointermove="draw"
                    @pointerup="stopDrawing"
                    @pointerleave="stopDrawing"
                    @pointercancel="stopDrawing"
                    @touchmove.prevent
                />
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2 sm:gap-3 pt-2 border-t">
            <Button
                type="button"
                variant="outline"
                class="w-full sm:w-auto"
                @click="emit('cancel')"
            >
                Annuller
            </Button>
            <Button
                type="button"
                class="w-full sm:w-auto"
                @click="saveImage"
            >
                <Check class="h-4 w-4 mr-2" />
                Brug anonymiseret billede
            </Button>
        </div>
    </div>
</template>

