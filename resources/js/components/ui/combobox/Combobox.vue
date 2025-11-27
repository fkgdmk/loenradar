<script setup lang="ts">
import { CheckIcon, ChevronsUpDownIcon } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/components/ui/command'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'

interface Option {
  value: string | number
  label: string
  searchText?: string // Optional field for search - if not provided, uses label
}

interface Props {
  options: Option[]
  modelValue?: string | number | null
  placeholder?: string
  emptyText?: string
  searchPlaceholder?: string
  class?: string
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Vælg...',
  emptyText: 'Ingen resultater fundet.',
  searchPlaceholder: 'Søg...',
})

const emits = defineEmits<{
  'update:modelValue': [value: string | number | null | undefined]
}>()

const open = ref(false)
const value = ref<string | number | null | undefined>(props.modelValue)
const searchTerm = ref('')

const selectedOption = computed(() =>
  props.options.find(option => option.value === value.value),
)

const filteredOptions = computed(() => {
  if (!searchTerm.value) return props.options
  const search = searchTerm.value.toLowerCase()
  return props.options.filter(option => {
    const searchText = option.searchText ?? option.label
    return searchText.toLowerCase().includes(search)
  })
})

function selectOption(selectedValue: string | number) {
  const newValue = selectedValue === value.value ? null : selectedValue
  value.value = newValue
  emits('update:modelValue', newValue === null ? null : newValue)
  open.value = false
  searchTerm.value = ''
}

watch(() => props.modelValue, (newValue) => {
  value.value = newValue ?? null
})

watch(open, (isOpen) => {
  if (!isOpen) {
    searchTerm.value = ''
  }
})
</script>

<template>
  <Popover v-model:open="open">
    <PopoverTrigger as-child>
      <Button
        variant="outline"
        role="combobox"
        :aria-expanded="open"
        :class="cn('w-full justify-between', props.class)"
      >
        {{ selectedOption?.label || props.placeholder }}
        <ChevronsUpDownIcon class="ml-2 h-4 w-4 shrink-0 opacity-50" />
      </Button>
    </PopoverTrigger>
    <PopoverContent align="start" class="w-full p-0" :style="{ width: 'var(--radix-popover-trigger-width)' }">
      <Command>
        <CommandInput
          v-model="searchTerm"
          :placeholder="props.searchPlaceholder"
          class="h-9"
        />
        <CommandList>
          <CommandEmpty v-if="filteredOptions.length === 0">
            {{ props.emptyText }}
          </CommandEmpty>
          <CommandGroup v-else>
            <CommandItem
              v-for="option in filteredOptions"
              :key="String(option.value)"
              @select="selectOption(option.value)"
            >
              <CheckIcon
                :class="cn(
                  'mr-2 h-4 w-4',
                  value === option.value ? 'opacity-100' : 'opacity-0',
                )"
              />
              {{ option.label }}
            </CommandItem>
          </CommandGroup>
        </CommandList>
      </Command>
    </PopoverContent>
  </Popover>
</template>

